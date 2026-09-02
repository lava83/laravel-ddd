# Laravel DDD

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lava83/laravel-ddd.svg)](https://packagist.org/packages/lava83/laravel-ddd)
[![License](https://img.shields.io/packagist/l/lava83/laravel-ddd.svg)](LICENSE.md)

> Work in progress. The public API may still change between releases.

Foundational building blocks for Domain-Driven Design in Laravel. The package ships battle-tested base classes and contracts — Entities, Aggregates, Value Objects, Repositories, Entity ↔ Model Mappers and Domain Events — so your applications can focus on the domain instead of the plumbing.

It enforces a strict layer separation (`Domain`, `Application`, `Infrastructure`) and gives you optimistic locking, automatic domain-event dispatching on save, and a set of ready-made value objects out of the box.

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/contracts` `^13.0`)

## Installation

```bash
composer require lava83/laravel-ddd
```

The service provider and the `LaravelDdd` facade are registered automatically through Laravel package discovery. There are no migrations to publish, and no configuration is required — you build your own domains on top of the provided base classes, as shown below. An optional config file tunes the `make:aggregate` scaffolder (see [Scaffolding](#scaffolding)).

## Quick start

The example models a single `Article` aggregate with a `Title` value object and persists it through a mapper and a repository. It is the smallest slice that still exercises every core building block: a value object, an aggregate, an Eloquent model, a mapper and a repository.

Suggested structure inside a consuming application:

```
app/
├── Domain/
│   └── Blog/
│       ├── Article.php
│       ├── Contracts/
│       │   └── ArticleRepository.php
│       └── ValueObjects/
│           ├── ArticleId.php
│           └── Title.php
├── Infrastructure/
│   ├── Mappers/
│   │   └── ArticleMapper.php
│   ├── Models/
│   │   └── ArticleModel.php
│   └── Repositories/
│       └── EloquentArticleRepository.php
└── Providers/
    └── BlogServiceProvider.php
```

### 1. Value objects

An identity and a small, self-validating value object. Both are immutable.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog\ValueObjects;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

final class ArticleId extends Uuid {}
```

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog\ValueObjects;

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

final class Title extends ValueObject
{
    private function __construct(private readonly string $value) {}

    /**
     * @throws ValidationException
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 255) {
            throw new ValidationException('Title must be between 1 and 255 characters.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### 2. Aggregate

State changes go through `updateAggregateRoot()`, which tracks the change and bumps the version (and can record a domain event — see [What else is in the box](#what-else-is-in-the-box)). Business rules live here, never in the application or infrastructure layer.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog;

use App\Domain\Blog\ValueObjects\ArticleId;
use App\Domain\Blog\ValueObjects\Title;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

final class Article extends Aggregate
{
    public function __construct(
        private readonly ArticleId $id,
        private Title $title,
    ) {
        parent::__construct();
    }

    public static function create(ArticleId $id, Title $title): self
    {
        return new self($id, $title);
    }

    public function id(): ArticleId
    {
        return $this->id;
    }

    public function title(): Title
    {
        return $this->title;
    }

    public function rename(Title $title): void
    {
        $this->updateAggregateRoot(['title' => $title]);
    }

    /**
     * Rebuild the aggregate from its persisted state.
     *
     * @deprecated Prefer the mapper for hydration. The base Entity still
     *             declares this as an abstract hook, so it is implemented
     *             here for completeness.
     */
    public static function fromState(Model $state): static
    {
        /** @var \App\Infrastructure\Models\ArticleModel $state */
        return new self(
            ArticleId::fromString((string) $state->id),
            Title::fromString((string) $state->title),
        );
    }
}
```

### 3. Eloquent model & migration

Extend the package `Model` — it provides a UUID primary key (via the `HasUuids` concern), version tracking, timestamp casts and a filtering layer. Point the model at its entity so `toEntity()` can resolve the mapper.

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

The `id`, `version`, `created_at` and `updated_at` columns are handled by the base model, so the migration only adds them plus your own fields:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
```

### 4. Mapper

The mapper is the single translation point between the domain and the database. `findOrCreateModelFillData()` (from the base mapper) loads or creates the row and fills the shared columns (`id`, `version`, timestamps) for you.

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

        // Restore version and timestamps from the persisted row.
        $article->hydrate($model);

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

### 5. Repository

Keep the contract in the domain layer and the Eloquent implementation in the infrastructure layer. The base `Repository` provides `saveEntity()` / `deleteEntity()`, a version-guarded optimistic-locking check, a `syncDependencies()` hook for persisting dependent rows, and automatic domain-event dispatching; you add the read methods your application needs.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog\Contracts;

use App\Domain\Blog\Article;
use App\Domain\Blog\ValueObjects\ArticleId;
use Lava83\LaravelDdd\Domain\Contracts\Repository;

interface ArticleRepository extends Repository
{
    public function nextId(): ArticleId;

    public function save(Article $article): void;

    public function find(ArticleId $id): ?Article;

    public function findOrFail(ArticleId $id): Article;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Blog\Article;
use App\Domain\Blog\Contracts\ArticleRepository;
use App\Domain\Blog\ValueObjects\ArticleId;
use App\Infrastructure\Models\ArticleModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;
use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;

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
        return ArticleModel::query()->find($id->value())?->toEntity();
    }

    public function findOrFail(ArticleId $id): Article
    {
        return ArticleModel::query()->findOrFail($id->value())->toEntity();
    }

    public function exists(Uuid $id): bool
    {
        return ArticleModel::query()->whereKey($id->value())->exists();
    }

    public function delete(Uuid $id): void
    {
        $model = ArticleModel::query()->find($id->value());

        if ($model !== null) {
            $this->deleteEntity($model->toEntity());
        }
    }

    /**
     * @return Collection<int, Article>
     */
    public function all(): Collection
    {
        return ArticleModel::query()
            ->get()
            ->map(fn (ArticleModel $model): Article => $model->toEntity());
    }

    public function count(): int
    {
        return ArticleModel::query()->count();
    }
}
```

#### Persisting dependent rows: the `syncDependencies()` hook

`saveEntity()` persists the aggregate's own row, then calls a protected `syncDependencies()` hook, and only **after** that dispatches the aggregate's domain events (which are deferred to the surrounding transaction's commit). Override the hook to persist child rows or pivot records that belong to the same write — they land *before* any event fires, and inside your `DB::transaction()`:

```php
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

// in EloquentArticleRepository

/**
 * @param  Article       $aggregate
 * @param  ArticleModel  $model
 */
protected function syncDependencies(Aggregate $aggregate, Model $model): void
{
    // $model is the row that was just persisted; associate related records to
    // it here. The aggregate's domain events dispatch only after this returns.
}
```

The base implementation is an empty no-op, so repositories with no dependent rows override nothing. Because dispatch is deferred to the transaction commit, the events for the aggregate *and* everything `syncDependencies()` writes are published together once the whole `save()` commits — and dropped together if it rolls back.

### 6. Wire it up

Register the mapper with the resolver and bind the repository contract to its implementation. A dedicated service provider keeps this in one place.

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Blog\Article;
use App\Domain\Blog\Contracts\ArticleRepository;
use App\Infrastructure\Mappers\ArticleMapper;
use App\Infrastructure\Repositories\EloquentArticleRepository;
use Illuminate\Support\ServiceProvider;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ArticleRepository::class, EloquentArticleRepository::class);
    }

    public function boot(): void
    {
        entity_mapper_resolver()->registerMapper(Article::class, new ArticleMapper());
    }
}
```

Register the provider in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BlogServiceProvider::class,
];
```

### 7. Use it

```php
use App\Domain\Blog\Article;
use App\Domain\Blog\Contracts\ArticleRepository;
use App\Domain\Blog\ValueObjects\Title;

$repository = app(ArticleRepository::class);

// Create and persist a new article.
$article = Article::create($repository->nextId(), Title::fromString('Hello, DDD'));
$repository->save($article);

// Load it, change it through the domain, persist again.
$loaded = $repository->findOrFail($article->id());
$loaded->rename(Title::fromString('Hello, Domain-Driven Design'));
$repository->save($loaded); // version is bumped; optimistic locking guards concurrent writes
```

## Scaffolding

Rather than writing every class by hand (as the [Quick start](#quick-start) does), `make:aggregate` generates the building blocks for an aggregate in a bounded context: the identity value object, the aggregate root and the Eloquent model, and — optionally — a repository (contract plus Eloquent implementation) and an entity mapper.

Run it interactively and answer the prompts (aggregate name, bounded context, identity type, and whether to also create a repository and a mapper):

```bash
php artisan make:aggregate
```

Or pass everything up front:

```bash
php artisan make:aggregate Order OrderProcessing --with-repository --with-entity-mapper --id-type=uuid
```

### Options

- `name` — the aggregate name (e.g. `Order`); prompted when omitted.
- `bounded-context` — the context it lives in (e.g. `OrderProcessing`); prompted when omitted.
- `--with-repository` — also generate the repository contract and its Eloquent implementation.
- `--with-entity-mapper` — also generate the entity mapper.
- `--id-type=` — identity type, `uuid` (default) or `integer`.
- `--force` — overwrite existing files instead of skipping them.

In non-interactive contexts (e.g. CI) the prompts are skipped: arguments and options drive everything, the identity type defaults to `uuid`, and the repository and mapper are generated only when their flags are present.

### What it generates

Always:

- **`{Name}Id`** — identity value object extending the package `Uuid` or `Integer` base.
- **`{Name}`** — the aggregate root, typed `Aggregate<{Name}Model, {Name}Id>`, with `create()` / `fromState()` factory methods and a `validate()` hook.
- **`{Name}Model`** — an Eloquent model extending the package `Model`, with a `#[Table]` attribute derived from the snake-cased plural name (and the `HasUuids` concern for UUID identities).

On request:

- `--with-repository` → **`{Name}RepositoryContract`** (`findAll`, `findOrFail`, `save`, `remove`) and **`Eloquent{Name}Repository`**.
- `--with-entity-mapper` → **`{Name}Mapper`** with `toEntity()` / `toModel()`.

The generated files are skeletons: the model and mapper carry a `name` placeholder column, and the aggregate's `validate()` and the mapper's `toModel()` are left for you to complete. Existing files are reported as `SKIPPED (exists)` and left untouched unless you pass `--force`. After writing, the command prints the service-provider bindings to register — the mapper via `entity_mapper_resolver()->registerMapper(...)` and the repository via `$this->app->bind(...)` — and warns if the target root namespace isn't autoloaded yet.

### Namespaces

Two keys in `config/laravel-ddd.php` decide where the classes land:

- `bounded_contexts_root_namespace` — root namespace for generated code (default `App\BoundedContexts`).
- `bounded_contexts_without_own_layers` — whether bounded contexts share the layer namespaces (default `true`).

With the defaults, `make:aggregate Order OrderProcessing --with-repository --with-entity-mapper` writes:

```
App\BoundedContexts\Domain\OrderProcessing\Aggregates\Order
App\BoundedContexts\Domain\OrderProcessing\ValueObjects\Identity\OrderId
App\BoundedContexts\Domain\OrderProcessing\Contracts\OrderRepositoryContract
App\BoundedContexts\Infrastructure\OrderProcessing\Models\OrderModel
App\BoundedContexts\Infrastructure\OrderProcessing\Mappers\OrderMapper
App\BoundedContexts\Infrastructure\OrderProcessing\Repositories\EloquentOrderRepository
```

Set `bounded_contexts_without_own_layers` to `false` and each context owns its layers instead — the context and layer segments swap, e.g. `App\BoundedContexts\OrderProcessing\Domain\Aggregates\Order`.

Target paths are resolved from your `composer.json` PSR-4 map; if the root namespace isn't mapped yet, the command prints the `autoload` entry to add and reminds you to run `composer dump-autoload`.

Publish the config to change these defaults:

```bash
php artisan vendor:publish --tag=ddd-config
```

## What else is in the box

Beyond the slice above, the package provides an `AggregateRoot` contract with domain-event recording: events collected through `updateAggregateRoot($changes, $eventClass)` are dispatched automatically via Laravel's event system once the surrounding transaction commits — deferred with `DB::afterCommit()` — then cleared from the aggregate. Every aggregate carries a `version` for optimistic locking and raises a `ConcurrencyException` on conflicting writes. You also get a growing catalogue of ready-made value objects — `Uuid`, `Email`, `Phonenumber`, `Money`, `Link`, `Json`, `GeoAddress` and more — plus a fluent Eloquent filtering layer on the base `Model` (see [Filtering](#filtering)).

## Filtering

The base `Model` ships with a filtering layer built on [`indexzer0/eloquent-filtering`](https://github.com/IndexZer0/eloquent-filtering). `Infrastructure\Models\Filter\Builder` composes a set of filters fluently and serialises them — via `toArray()` — to the operator-array shape the model's `filter()` query scope consumes.

### Building and applying filters

```php
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;

$builder = Builder::make()
    ->like('title', 'DDD')
    ->eq('status', 'published')
    ->in('category', ['laravel', 'php'])
    ->gte('reading_time', 5)
    ->isNull('archived_at');

// `filter()` is a query scope from the Filterable trait; each filter must be
// permitted by the model's allowedFilters() list.
$articles = ArticleModel::query()->filter($builder->toArray())->get();
```

`toArray()` produces one MongoDB-style operator entry per filter (`$eq`, `$gte`, `$in`, `$null`, …):

```php
[
    ['type' => '$like', 'target' => 'title',        'value' => 'DDD'],
    ['type' => '$eq',   'target' => 'status',       'value' => 'published'],
    ['type' => '$in',   'target' => 'category',     'value' => ['laravel', 'php']],
    ['type' => '$gte',  'target' => 'reading_time', 'value' => 5],
    ['type' => '$null', 'target' => 'archived_at',  'value' => true],
]
```

### Reconstructing filters from a request

`Builder::fromArray()` is the inverse of `toArray()` — it rebuilds a `Builder` from that array shape, for example from filters that arrive over HTTP. It validates strictly and throws `Filter\Filters\Exceptions\FilterArrayNotValid` on a missing key, an unknown operator, or a value whose type does not match the operator.

Because the check is strict, **carry the filters as JSON rather than as bracket-notation query parameters.** PHP parses every query-string value as a string, so `?filters[0][type]=$gte&filters[0][value]=18` yields the string `"18"` — which the numeric operators (`$gt`, `$gte`, `$lt`, `$lte`) and `$null` reject, while string-friendly operators like `$eq` and `$in` would still pass, making bracket notation deceptively half-working. A JSON payload preserves `int` and `bool`:

```text
GET /api/articles?filters=[{"type":"$like","target":"title","value":"DDD"},{"type":"$eq","target":"status","value":"published"},{"type":"$in","target":"category","value":["laravel","php"]},{"type":"$gte","target":"reading_time","value":5},{"type":"$null","target":"archived_at","value":true}]
```

> URL-encode the `filters` value in practice (`[` → `%5B`, `"` → `%22`, `$` → `%24`, …); it is shown decoded here for readability.

On the server, decode the JSON and hand the array to `fromArray()`:

```php
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;

/** @var array<int, array<string, mixed>> $decoded */
$decoded = json_decode($request->query('filters', '[]'), true, flags: JSON_THROW_ON_ERROR);

$articles = ArticleModel::query()->filter(Builder::fromArray($decoded)->toArray())->get();
```

The request above reconstructs exactly:

```php
Builder::make()
    ->like('title', 'DDD')
    ->eq('status', 'published')
    ->in('category', ['laravel', 'php'])
    ->gte('reading_time', 5)
    ->isNull('archived_at');
```

## Development

```bash
composer test          # run the Pest test suite
composer test-coverage # run tests with an HTML coverage report
composer format        # apply the Laravel Pint code style
composer lint          # Pint + PHPStan (Larastan) + Deptrac layering
composer qa            # lint + tests
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
