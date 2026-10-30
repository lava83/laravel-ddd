# Laravel DDD

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lava83/laravel-ddd.svg)](https://packagist.org/packages/lava83/laravel-ddd)
[![License](https://img.shields.io/packagist/l/lava83/laravel-ddd.svg)](LICENSE.md)

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
    private function __construct(private readonly string $value)
    {
        parent::__construct();
    }

    /**
     * @throws ValidationException
     */
    public static function fromString(string $value): self
    {
        return new self(trim($value));
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

    protected function validate(): array
    {
        if ($this->value === '' || mb_strlen($this->value) > 255) {
            return ['value' => ['Title must be between 1 and 255 characters.']];
        }

        return [];
    }
}
```

Every value object implements `validate()` (error messages keyed by property name, `[]` when valid) and calls `parent::__construct()` in its constructor, which throws a `ValidationException` carrying those errors.

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
     * This is the hook the mapper calls to hydrate the aggregate. It is
     * the one place where the Domain touches an Eloquent model; the
     * `InfraModel` layer in `deptrac.yaml` permits exactly that.
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

Beyond the slice above, the package provides an `AggregateRoot` contract with domain-event recording: events collected through `updateAggregateRoot($changes, $eventClass)` are dispatched automatically via Laravel's event system once the surrounding transaction commits — deferred with `DB::afterCommit()` — then cleared from the aggregate. With an `Integer` identity assigned on insert, events recorded before the first save (such as a `…Created` event in a `create()` factory) are moved onto the persisted key before they are dispatched. Every aggregate carries a `version` for optimistic locking and raises a `ConcurrencyException` on conflicting writes. You also get a growing catalogue of ready-made value objects — `Uuid`, `Email`, `Phonenumber`, `Money`, `Link`, `Json`, `GeoAddress` and more — plus a fluent Eloquent filtering layer on the base `Model` (see [Filtering](#filtering)), with free-text search and facet counts for read-model lists (see [Search](#search) and [Facets](#facets)).

## API-backed aggregates

Aggregates that live behind an HTTP API instead of a table use `Infrastructure\Repositories\ApiRepository`. It is opt-in: the package binds nothing, so pure database users never see it.

Reading wraps the `data` payload of `GET {resource}/{id}` in a `RemoteModel` (an attribute bag that refuses `save()`, `delete()` and queries). Writing is **command-based, not diff-based**: every recorded domain event is translated to the API call that realises it. Events without a call throw `UnmappedDomainEvent` before the first request. The calls run in recording order and are **not atomic** — if one fails, the earlier ones have already happened and the events stay on the aggregate. Events are published only after the last call succeeded.

```php
final class ApiArticleRepository extends ApiRepository implements ArticleRepository
{
    protected ?string $entityClassName = Article::class;

    protected string $modelClass = ArticleModel::class; // extends RemoteModel

    public function findOrFail(ArticleId $id): Article
    {
        /** @var Article */
        return $this->fetchOne($id->value())->toEntity();
    }

    public function save(Article $article): void
    {
        $this->persistCommands($article);
    }

    protected function resource(): string
    {
        return 'v1/articles';
    }

    protected function makeRemoteModel(array $payload): RemoteModel
    {
        return ArticleModel::fromAttributes($payload);
    }

    protected function commands(): array
    {
        return [
            ArticleCreated::class => fn (Article $a): Response => $this->api->post($this->resource(), [...]),
            ArticleRenamed::class => fn (Article $a): Response => $this->api->patch(
                "{$this->resource()}/{$a->id()->value()}/title",
                ['title' => (string) $a->title()],
            ),
        ];
    }
}
```

The `ApiClient` is built by the host in its own service provider:

```php
$config = new ApiClientConfig(baseUrl: 'https://api.example.com', timeout: 30);

$this->app->singleton(ApiClient::class, fn () => new ApiClient(
    $config,
    new ClientCredentialsAuthenticator($config, clientId: '…', clientSecret: '…', tokenPath: 'v1/oauth/token'),
));
```

- Authentication is an `ApiAuthenticator` (`authorize(PendingRequest)`): `ClientCredentialsAuthenticator` (token cached as a plain string) or `StaticBearerAuthenticator`. Implement the interface for API keys or basic auth. An authenticator that also implements `RefreshableAuthenticator` gets one repeat after a 401; fixed credentials get none.
- Only GETs are retried (connection errors and 5xx, `ApiClientConfig::$readAttempts` total attempts). Writes never are — there are no idempotency keys.
- Failures surface as `Infrastructure\Api\Exceptions\ApiException` subclasses (`Unauthorized`, `Forbidden`, `NotFound`, `ValidationFailed`, `Unavailable`); callers never see Laravel's `RequestException`. A 404 in `fetchOne()` becomes a `ModelNotFoundException`.
- Several APIs: build one `ApiClient` (or a thin subclass to type-hint it) per API.

## Cursor pagination for read models

List endpoints that return read models should paginate with `Infrastructure\Pagination\ReadModelCursorPaginator`. Laravel reads the cursor values from the page items under the ORDER BY column name, so mapping a page with `through()` breaks as soon as the sort column (`account_id`) is not a property of the read model (`id`). `ReadModelCursorPaginator` keeps the source model of every read model and reads the cursor from there:

```php
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;

$page = ReadModelCursorPaginator::fromPage(
    AccountModel::query()->orderBy('account_id')->cursorPaginate(200),
    fn (AccountModel $model) => AccountReadModel::fromModel($model),
);
```

The result is a regular `Illuminate\Pagination\CursorPaginator`, so `Resource::collection($page)` produces `data`, `links` and `meta` as usual. The ordering must be unique (use the primary key).

### Reading a paginated list from an API

`ReadModelCursorPaginator` serves the producing side: it starts from an Eloquent query. The consuming side has no query and no model, only the `data` and `meta` of an API answer and cursor tokens it must not interpret. For that, `Infrastructure\Pagination\ApiCursorPageMapper` reads the answer of a Laravel-style cursor page and returns a `RelayedCursorPaginator`, which hands the tokens on unchanged:

```php
use Lava83\LaravelDdd\Infrastructure\Pagination\ApiCursorPageMapper;

$pages = new ApiCursorPageMapper; // parameter names are configurable: per_page, cursor, filter

$response = $api->get('v1/accounts', $pages->query(
    filter: ['status' => 'active'],
    perPage: 50,
    cursor: $request->query('cursor'),
));

return $pages->fromResponse(
    $response,
    path: '/accounts', // your own endpoint, so the links never point at the API
    mapItem: fn (array $payload) => AccountReadModel::fromArray($payload),
);
```

`Resource::collection($page)` then produces `data`, `links` and `meta` as usual. Nothing is derived from the items, so the read models need no sort column.

The mapper fails loudly with an `ApiException` if the answer is not a cursor page: no `data` list, no `meta`, no integer `meta.per_page`, no `meta.next_cursor` / `meta.prev_cursor` key, or a token that is not a Laravel cursor. A missing `meta` must never read as "last page, no results". An empty cursor string counts as no cursor.

The mapper expects the shape a Laravel resource collection produces. For an API with another shape (other keys, other token format), write a small mapper of your own and build a `RelayedCursorPaginator` from the tokens: the paginator knows no token format.

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

### Custom filters

Built-in operators cannot express every predicate — for example a tenant scope that is an OR over two columns, or that needs a subquery against another table or connection. Model such predicates as an `indexzer0/eloquent-filtering` **custom filter**: a virtual target implemented inside the model's infrastructure, addressed from the outside purely through the builder. This is the recommended way to express tenant/scope predicates that need cross-table or cross-connection subqueries.

**1. Implement and register the `FilterMethod`.** The constructor parameter is named after the request key (`value`):

```php
use Illuminate\Database\Eloquent\Builder;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterMethod;
use IndexZer0\EloquentFiltering\Filter\Traits\FilterMethod\FilterContext\CustomFilter;

final class BranchScopeFilter implements FilterMethod
{
    use CustomFilter;

    public function __construct(private readonly int $value) {}

    public static function type(): string
    {
        return '$branchScope';
    }

    public function apply(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('b2b_user_id', fn ($sub) => $sub->select('id')->from('users')->where('branch_id', $this->value))
            ->orWhereIn('b2c_user_id', fn ($sub) => $sub->select('id')->from('users')->where('branch_id', $this->value)));
    }
}
```

```php
// config/eloquent-filtering.php
'custom_filters' => [
    BranchScopeFilter::class,
],
```

**2. Allow it on the model.** `allowedFilters()` remains the authoritative gate: a model that does not list the type rejects it according to the `eloquent-filtering` `suppress` config.

```php
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;

public function allowedFilters(): AllowedFilterList
{
    return Filter::only(Filter::custom('$branchScope'));
}
```

**3. Build it.** `Builder::custom(string $type, mixed $value = true, ?string $target = null)` accepts a scalar, or a (nested) array/`Collection` of scalars. `target` is optional and only serialised when given:

```php
$filter = Builder::make()->custom('$branchScope', $branch->getKey());
// [['type' => '$branchScope', 'value' => 42]]

AccountModel::query()->filter($filter->toArray())->get();
```

A custom type may not reuse a built-in operator name (`CustomFilterNotValid`), and an invalid value throws `FilterValueNotValid`.

**Reconstructing from a request.** `fromArray()` stays strict: an unknown type throws `FilterArrayNotValid` unless it is listed in the package config. Custom entries do not need a `target`; built-ins still do.

```php
// config/laravel-ddd.php
'filters' => [
    'custom_types' => ['$branchScope'],
],
```

**Merging.** `MergeStrategy::KeepExisting` is unchanged. With `MergeStrategy::Override`, an incoming custom filter replaces existing ones with the same type string and target (no target equals no target).

### Removing client filters (`without()`)

`without(string ...$targets)` returns a new `Builder` without every filter whose target is one of `$targets`, built-in and custom alike. It never changes the builder it is called on, unlike `eq()`, `custom()` and the other operators, which add to the builder in place.

```php
$requested = Builder::make()
    ->eq('status_id', 2)
    ->in('user_type_id', [1, 3])
    ->custom('$b2cUserSearch', 'müller');

$requested->without('status_id')->toArray();
// [
//     ['type' => '$in', 'target' => 'user_type_id', 'value' => [1, 3]],
//     ['type' => '$b2cUserSearch', 'value' => 'müller'],
// ]
```

Custom filters without a target are never removed, an unknown target is a no-op, and `without()` with no arguments returns an equal copy.

> Call `without()` on client (request) filters only, never on a scope filter. A scope can use the same target as a facet (`branch_id`), and removing it widens what the caller sees.

### Search

`Filter\Methods\TokenSearchFilterMethod` is an abstract custom `FilterMethod` for a free-text search over several columns. Extend it with the custom type and the columns:

```php
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;

final class B2cUserSearchFilter extends TokenSearchFilterMethod
{
    public static function type(): string
    {
        return '$b2cUserSearch';
    }

    protected function columns(): array
    {
        return ['email', 'data.firstname', 'data.lastname', 'data.personalnr'];
    }
}
```

- The phrase is split on Unicode whitespace. Duplicates are dropped case-insensitively, and only the first `maxTokens()` tokens (default 5) are used. An empty phrase adds no constraint.
- Every token must match (AND). A token matches when any column matches (OR).
- `email` is a column of the model. `data.lastname` is a column of the `data` relation, resolved with `whereHas()`: the part before the last dot is the relation, which must live on the same connection. Columns of one relation share one `EXISTS` per token.
- `mode()` returns `SearchMode::Prefix` (`token%`, the default) or `SearchMode::Contains` (`%token%`). Prefix can use a B-tree index on the column, Contains scans it.
- `%`, `_` and `!` in a token are literals. The filter escapes them and adds an explicit `ESCAPE '!'`, so it behaves the same on MySQL/MariaDB (with or without `NO_BACKSLASH_ESCAPES`), SQLite and PostgreSQL.
- Case sensitivity follows the column collation. A `utf8mb4_*_ci` collation on MariaDB matches `müller` to `Müller`, and whether `ß` matches `ss` depends on the collation. SQLite folds ASCII only, and PostgreSQL's `LIKE` is always case-sensitive.
- The value must be a UTF-8 string or an integer. Anything else fails as a `MalformedFilterFormatException`.

Register the type in `eloquent-filtering.custom_filters` and allow it on the model, like any custom filter. **Do not** list it in `laravel-ddd.filters.custom_types`. Let the request read a dedicated `search` parameter and add the filter with `Builder::custom()`, so a client cannot send it through `filters`:

```php
// config/eloquent-filtering.php
'custom_filters' => [
    B2cUserSearchFilter::class,
],
```

```php
// the model
public function allowedFilters(): AllowedFilterList
{
    return Filter::only(
        Filter::field('status_id', [FilterType::EQUAL, FilterType::IN]),
        Filter::field('user_type_id', [FilterType::EQUAL, FilterType::IN]),
        Filter::custom('$b2cUserSearch'),
    );
}
```

```php
// the request
$requested = Builder::fromArray($decodedFilters);

$search = $request->string('search')->trim()->value();

if ($search !== '') {
    $requested->custom(B2cUserSearchFilter::type(), $search);
}
```

## Facets

Facet counts are counts per value of one attribute of a list, for example how many users each status tab of an admin UI holds. They are counted on the database, with the same base query the list uses for its page.

**Read models (Domain).** `Domain\ReadModels\Facets\FacetReadModel` (`key()`, `buckets()`, `toArray()`) holds `FacetBucketReadModel`s (`value()`, `count()`, `toArray()`):

```php
$facet->toArray();
// [['value' => null, 'count' => 3], ['value' => 1, 'count' => 120], ['value' => 2, 'count' => 7]]
```

Buckets are ordered by value ascending, the null bucket first. A NULL column value is a bucket of its own. Values without rows have no bucket, because a `GROUP BY` cannot know them: **a missing value means 0.**

**Definitions.** `Infrastructure\Models\Facets\FacetDefinition` says what to count:

```php
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;

FacetDefinition::column('status', 'status_id');           // filter target: status_id
FacetDefinition::column('type', 'user_type_id');          // filter target: user_type_id
FacetDefinition::relation('services', 'services', 'id');  // filter target: services
```

- `column($key, $column, $filterTarget = null)` counts the rows of the list model per value of one of its columns. The filter target defaults to `$column`.
- `relation($key, $relation, $column, $filterTarget = null)` counts the distinct rows of the list model per value of a column of a `HasMany` or `BelongsToMany` relation: a user with two services counts once for each. The filter target defaults to `$relation`. The related model must live on the list model's connection, otherwise `FacetNotCountable` is thrown. The list's keys go into a subquery, never through PHP memory.

**Counting.** `FacetCounter::count(Closure $baseQuery, Builder $scope, Builder $requested, FacetDefinition ...$facets)` returns a `Collection<int, FacetReadModel>` in the order of the definitions.

- `$baseQuery` is a `Closure(Filter\Builder): Eloquent\Builder`: the base query of the list page, including `->filter($filter->toArray())`. Eager loads, ordering, limit and offset are dropped before grouping.
- Each facet is counted with `$scope->merge($requested->without($facet->filterTarget()))`: the whole scope, plus every requested filter except the facet's own. The status tabs therefore show how many rows each status holds under the current search and type filter.
- **Keep the scope and the requested filters apart.** The scope (tenant, branch: what the caller may see at all) is never reduced. Merge the two before calling the counter, and a facet on `branch_id` drops the branch scope along with the client's branch filter.
- A column facet groups the list query with `count(*)`, so the base query must not join a to-many table, or a row counts once per joined row. A relation facet groups the related rows with `count(distinct …)`, and the soft-delete scopes of both models stay active.

### A list endpoint with search and facets

From the request to the response, with the scope and the request filters kept apart all the way down.

**Request.** The client's filters and the search, nothing else:

```php
final class ListB2cUsersRequest extends FormRequest
{
    public function requested(): Builder
    {
        /** @var array<int, array<string, mixed>> $decoded */
        $decoded = json_decode($this->string('filters', '[]')->value(), true, flags: JSON_THROW_ON_ERROR);

        $requested = Builder::fromArray($decoded);
        $search = $this->string('search')->trim()->value();

        if ($search !== '') {
            $requested->custom(B2cUserSearchFilter::type(), $search);
        }

        return $requested;
    }
}
```

**Use case.** It builds the scope from who is asking, and passes the request filters on untouched:

```php
final readonly class ListB2cUsers
{
    public function __construct(
        private B2cUserReadQuery $users,
    ) {}

    /**
     * @return array{page: CursorPaginator<int, B2cUserReadModel>, facets: Collection<int, FacetReadModel>}
     */
    public function __invoke(int $branchId, Builder $requested, int $perPage): array
    {
        $scope = Builder::make()->eq('branch_id', $branchId);

        return [
            'page' => $this->users->page($scope, $requested, $perPage),
            'facets' => $this->users->facets($scope, $requested),
        ];
    }
}
```

**Read query.** The page and the facets share one base query:

```php
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;

final class EloquentB2cUserReadQuery implements B2cUserReadQuery
{
    public function page(Builder $scope, Builder $requested, int $perPage): CursorPaginator
    {
        return ReadModelCursorPaginator::fromPage(
            $this->baseQuery($scope->merge($requested))->orderBy('id')->cursorPaginate($perPage),
            fn (B2cUserModel $model) => B2cUserReadModelMapper::fromModel($model),
        );
    }

    public function facets(Builder $scope, Builder $requested): Collection
    {
        return FacetCounter::count(
            fn (Builder $filter) => $this->baseQuery($filter),
            $scope,
            $requested,
            FacetDefinition::column('status', 'status_id'),
            FacetDefinition::column('type', 'user_type_id'),
        );
    }

    /**
     * @return EloquentBuilder<B2cUserModel>
     */
    private function baseQuery(Builder $filter): EloquentBuilder
    {
        return B2cUserModel::query()->with('data')->filter($filter->toArray());
    }
}
```

**Controller.** The facets go into `meta` next to the cursor:

```php
public function index(ListB2cUsersRequest $request, ListB2cUsers $listUsers): AnonymousResourceCollection
{
    $result = $listUsers($request->user()->branch_id, $request->requested(), $request->integer('per_page', 50));

    return B2cUserResource::collection($result['page'])->additional([
        'meta' => [
            'facets' => $result['facets']->mapWithKeys(
                fn (FacetReadModel $facet): array => [$facet->key() => $facet->toArray()],
            ),
        ],
    ]);
}
```

```json
{
    "data": ["…"],
    "links": {"first": null, "last": null, "prev": null, "next": "…"},
    "meta": {
        "path": "…",
        "per_page": 50,
        "next_cursor": "…",
        "prev_cursor": null,
        "facets": {
            "status": [{"value": null, "count": 3}, {"value": 1, "count": 120}, {"value": 2, "count": 7}],
            "type": [{"value": 1, "count": 98}, {"value": 2, "count": 32}]
        }
    }
}
```

A status the list does not contain has no bucket. Fill the tabs from your own status enum, with 0 for a missing value.

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
