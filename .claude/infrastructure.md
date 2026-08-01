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

- `saveEntity(Aggregate): Model` — maps to a model, persists **only if the aggregate is dirty or the row is new**, then re-hydrates the aggregate from the model
- `deleteEntity(Aggregate)`, `deleteEntities(Collection)`, `deleteRelatedEntity(Aggregate, string $relation, int|string $relatedId)`
- `handleOptimisticLocking(Aggregate, Model)`, `dispatchUncommittedEvents(Aggregate)`, `syncEntityFromModel(Aggregate, Model)`
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

- Wrap every write in `DB::transaction()`. The base class does not open one.
- Sync many-to-many relations **after** `saveEntity()` returns, inside the same transaction, using the returned model.
- Bind the domain contract to the Eloquent implementation in a service provider.

## Domain events

`saveEntity()` → `persistDirtyEntity()` → `dispatchUncommittedEvents()`, which hands `uncommittedEvents()` to `Services\DomainEventPublisher` and then marks them committed. The publisher pushes each event through Laravel's `Illuminate\Events\Dispatcher`, so domain events are ordinary Laravel events — listeners subscribe to the event class.

**Dispatch gate.** `persistDirtyEntity()` is the only dispatcher on the save path, and `saveEntity()` calls it only when `$aggregate->isDirty() || $model->exists === false`. An event recorded via `recordEvent()` without a tracked property change, on a row that already exists, is silently never published — and `saveEntity()` still returns normally, so the call looks successful. Record events through `updateAggregateRoot()`, or assert dispatch explicitly.

Events also fire from `deleteEntity()` and `deleteRelatedEntity()`. In all cases dispatch happens inside your transaction, so a later rollback cannot unsend them — keep listeners idempotent or queue them.

## Optimistic locking

`handleOptimisticLocking()` compares `$model->getAttribute('version')` with `$aggregate->version()` and throws `Exceptions\ConcurrencyException` on mismatch. `Entity::touch()` (protected) bumps the entity version on every applied change.

**As implemented it cannot detect a concurrent write.** `findOrCreateModelFillData()` fills `version` from the aggregate, `version` is fillable, and the comparison runs after that fill — both sides come from the same object, so the check is a tautology. There is no version-guarded `UPDATE ... WHERE version = ?` and no `lockForUpdate()` anywhere in `src/`. Treat the feature as a placeholder, not a guarantee, and don't cite it as working in review.

The inverse trap, if a mapper does *not* fill `version`: an aggregate mutated in memory to version 2 and then inserted as a new row hits the `?? DEFAULT_VERSION` fallback and throws `ConcurrencyException` spuriously.

## Exceptions

| Namespace | Class |
| --- | --- |
| `Infrastructure\Exceptions` | `CantSaveModel`, `CantDeleteModel`, `CantDeleteRelatedModel`, `ConcurrencyException` |
| `Infrastructure\Mappers\Exceptions` | `NoMapperFoundForEntity` |
| `Infrastructure\Models\Exceptions` | `EntityClassNotAvailable` |
| `Infrastructure\Repositories\Exceptions` | `EntityClassNotAvailable` |

The two `EntityClassNotAvailable` classes are distinct — check the namespace when catching or asserting.
