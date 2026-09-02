# Infrastructure layer — `src/Infrastructure/**`

Eloquent implementations of domain contracts: Models, Mappers, Repositories, and the event publisher.

Deptrac: `Infrastructure → Domain, Application, InfraModel, AllowedPrimitives`.

## Model

`Infrastructure\Models\Model` extends Eloquent and provides:

- `Filterable` (`indexzer0/eloquent-filtering`) with an `allowedFilters(): ?AllowedFilterList` hook that returns `null` by default
- `getFillable()` merging `['id', 'version', 'created_at', 'updated_at']` into the subclass `$fillable`
- `casts()` for `created_at`/`updated_at` (`datetime`) and `version` (`integer`)
- `toEntity(): Entity` and `entityMapper(): EntityMapper`, both resolving through `$entityClassName`

It does **not** provide UUID keys. Those are opt-in via the `Concerns\HasUuids` trait, which wraps Eloquent's `HasUuids` and generates UUIDv7.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Domain\Blog\Article;
use Lava83\LaravelDdd\Infrastructure\Models\Concerns\HasUuids;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * @property string $id
 * @property string $title
 */
final class ArticleModel extends Model
{
    use HasUuids;

    protected $table = 'articles';

    /** @var class-string<Article> */
    protected ?string $entityClassName = Article::class;

    /** @var list<string> */
    protected $fillable = ['title'];
}
```

Forgetting `$entityClassName` throws `Models\Exceptions\EntityClassNotAvailable` on the first `toEntity()` / `entityMapper()` call, not at boot.

`toEntity()` is declared `: Entity`, not `: Aggregate`. Passing its result straight into `Repository::deleteEntity(Aggregate $aggregate)` fails PHPStan level 6 — narrow with a `@var` annotation or go through a typed read method.

Migrations must add `id`, `version` and timestamps themselves — the base model manages them but does not create them:

```php
Schema::create('articles', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('title');
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

## Mapper

The single translation point between domain and database. Extend `Mappers\EntityMapper` and implement `Contracts\EntityMapper`.

Note the signature: `toEntity()` takes `Illuminate\Database\Eloquent\Model`, **not** the package `Model`. Annotate both `@extends` and `@implements` or the generics don't propagate.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappers;

use App\Domain\Blog\Article;
use App\Infrastructure\Models\ArticleModel;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper as BaseMapper;

/**
 * @extends BaseMapper<Article, ArticleModel>
 *
 * @implements EntityMapper<Article, ArticleModel>
 */
final class ArticleMapper extends BaseMapper implements EntityMapper
{
    /**
     * @param  ArticleModel  $model
     */
    public static function toEntity(EloquentModel $model, bool $deep = false): Article
    {
        /** @var ArticleModel $model */
        $article = Article::fromState($model);

        $article->hydrate($model); // restores version and timestamps

        return $article;
    }

    /**
     * @param  Article  $entity
     */
    public static function toModel(Entity $entity): ArticleModel
    {
        return self::findOrCreateModelFillData($entity, ArticleModel::class, [
            'title' => (string) $entity->title(),
        ]);
    }
}
```

Base helpers, both `protected static`:

- `findOrCreateModel(Entity $entity, string $modelClass)` — `findOr()` on the primary key, falling back to a fresh instance from the container.
- `findOrCreateModelFillData(Entity $entity, string $modelClass, array $data)` — the above, plus `fill()` with `id`, `version`, `created_at`, `updated_at` merged in front of `$data`.

The base class has no `toEntity()` — hydration direction is entirely yours, including calling `Entity::hydrate()` and handling `$deep`.

Rough edge: `findOrCreateModel()` passes the `Id` **object** into `findOr()` rather than `->value()`. It works via PDO string coercion. Don't copy the pattern into new code.

## Mapper registration

`EntityMapperResolver` is bound as a singleton to `EntityMapperResolverContract` in `LaravelDddServiceProvider`. Consumers register mappers in a provider's `boot()`:

```php
entity_mapper_resolver()->registerMapper(Article::class, new ArticleMapper());
```

An unregistered entity class throws `Mappers\Exceptions\NoMapperFoundForEntity` on resolve. The resolver is keyed by entity class, so `Collection<class-string<Entity>, EntityMapper>` — key type always spelled out.

## Repository

`Infrastructure\Repositories\Repository` is `@template TModel of Model, TAggregate of Aggregate<TModel, *>`. Set `$entityClassName` — the base reads exactly that property name — or `entityMapper()` throws `Repositories\Exceptions\EntityClassNotAvailable`.

The base gives you protected building blocks; the public read/write surface is yours:

- `saveEntity(Aggregate): Model` — maps to a model, persists **only if the aggregate is dirty or the row is new**, re-hydrates the aggregate, then runs the `syncDependencies()` hook, and finally dispatches the aggregate's uncommitted events
- `syncDependencies(Aggregate, Model)` — **no-op extension hook** run inside `saveEntity()` after the row is persisted and *before* events dispatch; override it to persist dependent rows (child records, pivot rows) that belong to the same write. See the Rules below.
- `deleteEntity(Aggregate)`, `deleteEntities(Collection)`, `deleteRelatedEntity(Aggregate, string $relation, int|string $relatedId)`
- `updateWithVersionGuard(Aggregate, Model)`, `dispatchUncommittedEvents(Aggregate)`, `syncEntityFromModel(Aggregate, Model)`, `makeModel(Aggregate): Model`
- `entityMapper(): EntityMapper`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

/**
 * @extends Repository<ArticleModel, Article>
 */
final class EloquentArticleRepository extends Repository implements ArticleRepository
{
    /** @var class-string<Article> */
    protected ?string $entityClassName = Article::class;

    public function nextId(): ArticleId
    {
        return ArticleId::generate();
    }

    public function save(Article $article): void
    {
        DB::transaction(fn () => $this->saveEntity($article));
    }

    public function find(ArticleId $id): ?Article
    {
        /** @var Article|null */
        return ArticleModel::query()->find($id->value())?->toEntity();
    }

    public function delete(Uuid $id): void
    {
        $article = $this->find(ArticleId::fromString($id->value()));

        if ($article !== null) {
            $this->deleteEntity($article);
        }
    }
}
```

Rules:

- Wrap every write in `DB::transaction()`. The base class does not open one — and event dispatch is now deferred to that transaction's commit (see Domain events), so the wrapper is what buys correct ordering and rollback safety, not just atomicity.
- Sync dependent rows (child records, pivot rows) by **overriding `syncDependencies(Aggregate, Model)`**, not after `saveEntity()` returns. The hook runs inside `saveEntity()` after the aggregate's own row is persisted and before its events dispatch, so relations are in place before any listener sees the event. The `$model` argument is the just-persisted row — associate related records to it.
- Bind the domain contract to the Eloquent implementation in a service provider.

## Domain events

`saveEntity()` runs `syncDependencies()` and then `dispatchUncommittedEvents()`, which hands `uncommittedEvents()` to `Services\DomainEventPublisher` and marks them committed. The publisher wraps each dispatch in `DB::afterCommit()` and pushes it through Laravel's `Illuminate\Events\Dispatcher`, so domain events are ordinary Laravel events — listeners subscribe to the event class.

**Dispatch is unconditional on the save path.** `dispatchUncommittedEvents()` is called on every `saveEntity()`, independent of the persist gate; it only no-ops when the aggregate has no uncommitted events. An event recorded without a tracked property change on an existing row is therefore still published — the earlier dirty-gated "silently dropped" behaviour is gone. Record state changes through `updateAggregateRoot()` for the version bump; a bare `recordEvent()` now dispatches too.

**Dispatch is deferred to the transaction commit.** Because the publisher uses `DB::afterCommit()`, events fire only when the outermost transaction commits — after `syncDependencies()` and after every dependent write. A rollback drops them; they are never sent for work that was undone. With **no** open transaction the dispatch runs immediately (the fail-safe), so ordering and rollback-safety hold only when the write is wrapped in `DB::transaction()`. Listeners run *after* commit, i.e. outside the aggregate's transaction — do not rely on a listener writing inside that same transaction.

Events also fire from `deleteEntity()` and `deleteRelatedEntity()`, through the same after-commit publisher.

## Optimistic locking

Updates go through `updateWithVersionGuard()`. It reads the base version from `$aggregate->persistedVersion()` (the version loaded from the row, not the in-memory one), sets the model's `version` to `base + 1`, and issues a guarded write — `UPDATE ... WHERE key = ? AND version = <base>`. When no row matches (`affected !== 1`) another process advanced the row in the meantime, and it throws `Exceptions\ConcurrencyException`.

**This is real optimistic locking** and is covered by `RepositoryTest` (a stale second writer is rejected; the row is left on the first write). It is *optimistic* only: a version-guarded `UPDATE`, no `lockForUpdate()`.

Because the guard keys off `persistedVersion()`, several in-memory mutations collapse into a single stored revision: two `updateAggregateRoot()` calls take the entity to in-memory version 3, but the store still moves `1 → 2` with no false conflict, and `syncEntityFromModel()` then re-hydrates the aggregate to the stored `2`. Inserts (`$model->exists === false`) skip the guard and go through `save()`.

## Exceptions

| Namespace | Class |
| --- | --- |
| `Infrastructure\Exceptions` | `CantSaveModel`, `CantDeleteModel`, `CantDeleteRelatedModel`, `ConcurrencyException` |
| `Infrastructure\Mappers\Exceptions` | `NoMapperFoundForEntity` |
| `Infrastructure\Models\Exceptions` | `EntityClassNotAvailable` |
| `Infrastructure\Repositories\Exceptions` | `EntityClassNotAvailable` |

The two `EntityClassNotAvailable` classes are distinct — check the namespace when catching or asserting.
