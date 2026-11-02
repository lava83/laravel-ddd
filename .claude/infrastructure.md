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

## API-backed repositories

`Repositories\ApiRepository` is the base for aggregates stored behind an HTTP API. Opt-in: no provider binding, no config file; the host builds `Api\ApiClient`.

- **Reads:** `fetchOne($id)` / `fetchAll($query)` call `GET {resource()}[/id]` and wrap each `data` payload through `makeRemoteModel()` in a `Models\RemoteModel`. A 404 becomes `ModelNotFoundException` naming `$modelClass`. A response without a `data` array throws `ApiException` (loud, never "no results").
- **Writes:** `persistCommands($aggregate)` — `commands()` maps `DomainEvent::class => Closure(Aggregate, DomainEvent): Response`. All uncommitted events must be mapped, else `UnmappedDomainEvent` is thrown **before the first call**. Calls run in recording order, the last response is `hydrate()`d back into the aggregate, then `dispatchUncommittedEvents()` runs. **Not atomic**: a failing call leaves earlier calls done and the events on the aggregate.
- Only `entityMapper()` and `dispatchUncommittedEvents()` of the base `Repository` are used; `saveEntity`, `makeModel` and the version guard assume a table. The API owns versioning, so there is no `ConcurrencyException` here.
- Event dispatch is not deferred to a transaction commit unless the host opens one; with no transaction it runs immediately.
- Register a mapper for the aggregate as usual (`entity_mapper_resolver()->registerMapper(...)`); `RemoteModel::toEntity()` goes through it, so set `$entityClassName` on the model.

`Api\ApiClient` is the one door to the API: `get/post/patch/delete`, authentication through `Api\Auth\ApiAuthenticator`, status → `Api\Exceptions\*` mapping, GET-only retries (`ApiClientConfig::$readAttempts` is the *total* number of attempts), one repeat after a 401 only for a `RefreshableAuthenticator`. `ClientCredentialsAuthenticator` caches the token as a string under a key derived from base URL and client id.

Tests: `tests/Foundation/Infrastructure/{Api,Repositories/ApiRepositoryTest.php,Models/RemoteModelTest.php}`, fixtures `tests/Fixtures/Infrastructure/Api`.

## Read queries and cursor pagination

Lists are served as read models (CQRS), not entities. Paginate the Eloquent query with `cursorPaginate()` and map the page with `Pagination\ReadModelCursorPaginator::fromPage()`:

```php
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;

return ReadModelCursorPaginator::fromPage(
    $this->baseQuery($filter)->orderBy('account_id')->cursorPaginate($perPage),
    fn (AccountModel $m) => AccountReadModelMapper::fromAccountModel($m),
);
```

Do **not** use `->through(...)` for this. Laravel reads `next_cursor` / `prev_cursor` from the page items under the ORDER BY column name. After `through()` the items are read models, so any sort column that is not also a read model property (`account_id` vs. `id`) throws `ErrorException: Undefined property`. A SELECT alias does not help, the cursor `WHERE` clause cannot use one.

`ReadModelCursorPaginator` extends `Illuminate\Pagination\CursorPaginator` (return types and Scramble keep working) and remembers each read model's source model in a `WeakMap`, so the cursor values come from there. The domain stays free of persistence knowledge.

- Generics: `@template TModel of Model`, `@template TReadModel of object`.
- The factory is called `fromPage`, **not** `map`: a static `map()` would shadow the paginator's `__call` forwarding to the collection, and Laravel calls `$paginator->map()` itself when serialising resource collections.
- `hasMore` is copied from the source page, not derived from `hasMorePages()` (which is always `true` for a "previous" cursor and would yield a wrong `prev_cursor` on the first page).
- `fromPage()` calls `withQueryString()` and keeps the query of the source page. Query parameters therefore stay in `links`.
- Lookup is by object identity. `filter()`, `reject()` and reordering the collection keep working, and `JsonResource` wrappers are unwrapped. If you *replace* the read models (e.g. `transform()` into new objects), the item itself is used as fallback, which fails again if it lacks the sort column. Cursor values are only read from the first and last item of the page.
- No resource class is needed: `XResource::collection($page)` yields `data`, `links` and `meta` (`path`, `per_page`, `next_cursor`, `prev_cursor`). Application must not depend on this class (Deptrac: `Application` does not see `Infrastructure`).
- Ordering must be unique and stable (primary key). A cursor that does not match the ordering throws `UnexpectedValueException` (Laravel behaviour, unchanged). The cursor rule of `PaginatesByCursor` rejects such a cursor as a 422 before the query runs, see "List requests and sorting".
- `getParametersForItem()` throws `Pagination\Exceptions\CursorValueIsNull` (a `LogicException`) when an order column of the item holds NULL. Laravel would build a cursor whose `>` / `<` never matches NULL and skip or repeat rows.

Tests: `tests/Foundation/Infrastructure/Pagination/ReadModelCursorPaginatorTest.php`, fixtures `CursorTest*`.

### Consuming a cursor page from an API

`ReadModelCursorPaginator` is the producing side (Eloquent query in). The consuming side (an API answer in, no query, no model) is `Pagination\RelayedCursorPaginator` plus `Pagination\ApiCursorPageMapper`. Both are additive and bound by nothing.

- `RelayedCursor extends Cursor` holds the token as received and returns it from `encode()`. `Cursor::encode()` rebuilds the token from decoded parameters, which is not guaranteed to give the same string back. The parameters stay empty, so `parameter()` throws; the direction comes from the position (`next()`, `previous()`). `fromEncoded()` is overridden to throw, because the inherited one would call the private constructor.
- `RelayedCursorPaginator extends CursorPaginator`. The constructor takes `(items, perPage, ?nextCursor, ?prevCursor, options, ?Cursor)`. `nextCursor()` / `previousCursor()` return the relayed tokens and derive nothing from the items. `hasMorePages()` and `onFirstPage()` are answered from the tokens (the parent derives them from the current cursor, and `hasMorePages()` is always true for a "previous" cursor). `setItems()` neither trims nor reverses. The paginator does **not** validate tokens: it knows no format.
- `ApiCursorPageMapper` knows the Laravel shape (`data`, `meta.per_page`, `meta.next_cursor`, `meta.prev_cursor`) and is strict: anything else throws `ApiException`, a token must decode via `Cursor::fromEncoded()`, a missing cursor key is an error (null and `''` are "no cursor"). `query()` builds the request query and leaves unset values out. Parameter names default to `per_page`, `cursor`, `filter` and are constructor arguments; `cursor` also names the cursor in the generated links.
- `fromResponse()` takes the application's own `$path` for the links and calls `withQueryString()`, so API URLs never leak into the response and the request query stays in the links. `links.first` / `links.last` are `null`, as for any cursor paginator.
- Why no `ApiRepository::fetchPage()`: the repository returns `RemoteModel`s for aggregates, a paginated list is read-model territory and also needed without a repository.
- Another API shape means another small mapper that feeds `RelayedCursorPaginator`; no option for foreign token formats exists on purpose.

Tests: `tests/Foundation/Infrastructure/Pagination/RelayedCursorPaginatorTest.php` and `ApiCursorPageMapperTest.php` (the latter includes a round trip through `ReadModelCursorPaginator`).

## Filter Builder

`Models\Filter\Builder` is a fluent, AND-only builder that serialises to the array shape `Model::filter()` (`indexzer0/eloquent-filtering`) consumes. It holds `Filters\Contracts\FilterDefinition` items:

- `FilterDefinition` — `identifier(): string`, `target(): ?string`, `value()`, `toArray()`. What `Builder` stores and what `merge()` compares.
- `FilterContract extends FilterDefinition` — the built-in operators (`Filters\Filter` subclasses); adds `type(): FilterType`, and `target()` is a non-null `string`. `identifier()` is `type->value`.
- `Filters\Custom` — an `eloquent-filtering` custom filter: free type string, optional target, value = scalar or (nested) array/`Collection` of scalars, validated in the constructor (`FilterValueNotValid`). `toArray()` omits `target` when null and turns Collections into arrays.

Rules:

- `Builder::custom($type, $value = true, $target = null)` rejects built-in operator names (`Exceptions\CustomFilterNotValid`). It does not consult the registry — only `fromArray()` does.
- `fromArray()` accepts an unknown type only if it is in `config('laravel-ddd.filters.custom_types')` (default `[]`); otherwise `FilterArrayNotValid::unknownType`. Custom rows treat a missing/`null` `target` as absent; built-ins still require it. No auto-derivation from `eloquent-filtering.custom_filters` — types are registered twice on purpose, so a client cannot reach filters just because some model registered them. `allowedFilters()` stays the gate at apply time.
- `merge(Override)` matches on `identifier()` + `target()` (`===`, so null equals null); `KeepExisting` is untouched.
- `without(string ...$targets)` returns a **new** Builder without every filter whose `target()` is in `$targets` (built-in and custom). Custom filters with a null target are never removed (`in_array(null, $strings, true)` is false), unknown targets are a no-op, no arguments return an equal copy. Only `merge()` and `without()` return new instances; `eq()`, `custom()` and the other operators add to the shared Collection and return `$this`, so a request filter reused for several facets must go through `without()`. Meant for client filters only — see "Facets and search".
- Recommended use: tenant/scope predicates needing cross-table or cross-connection subqueries — implement a `FilterMethod`, register it in `eloquent-filtering.custom_filters`, allow it with `Filter::custom('$name')`, build it with `Builder::custom()`. Worked example in the README ("Custom filters").

**Upgrade note (static typing only):** `Builder::filters()` now returns `Collection<int, FilterDefinition>` instead of `Collection<int, Filter>`, and `Builder::toArray()`'s shape makes `target` optional and `value` a nested-array-capable type. Code that calls `->type()` on items from `filters()` needs a `FilterContract` check/narrowing. No runtime change for built-in filters; their serialised output is byte-identical.

Tests: `tests/Foundation/Infrastructure/Models/Filter/CustomFilterIntegrationTest.php` runs a real custom `FilterMethod` (`tests/Fixtures/Infrastructure/Filters/OwnedByFilter.php`) against the `testing` DB; `TestCase` registers `EloquentFilteringServiceProvider` for it.

## Facets and search

Free-text search and facet counts for read-model lists, on the relational database and through the same read side as the page (`baseQuery()` → `Model::filter()` → `ReadModelCursorPaginator`). No search engine on purpose: tables written outside the domain would drift from an index, and tenant scoping would have to be duplicated in it.

### Facets

- Read models: `Domain\ReadModels\Facets\FacetReadModel` / `FacetBucketReadModel`, see `.claude/domain.md`.
- `Models\Facets\FacetDefinition` — `final readonly`, private constructor. Factories `column($key, $column, $filterTarget = null)` (target defaults to `$column`) and `relation($key, $relation, $column, $filterTarget = null)` (target defaults to `$relation`). The getters are `key()`, `columnName()`, `relationName()` and `filterTarget()`, because `column` and `relation` are taken by the static factories.
- `Models\Facets\FacetCounter::count(Closure $baseQuery, Builder $scope, Builder $requested, FacetDefinition ...$facets): Collection<int, FacetReadModel>`, in definition order. `@template TModel of Model` on `count()`, so a `Closure(Builder): Builder<UserModel>` type-checks (Eloquent's builder template is invariant).
- Per facet: `$scope->merge($requested->without($facet->filterTarget()), KeepExisting)` → `$baseQuery(…)` → `setEagerLoads([])`, `reorder()`, `limit`/`offset` set to null → `toBase()` (applies global scopes, soft deletes included) → group:
  - Column: `select <table.column> as facet_value, count(*) as facet_count … group by <table.column>`.
  - Relation: `Relation::noConstraints()` drops the parent constraint (BelongsToMany still joins the pivot). The related model's query gets `whereIn(<foreign key | foreign pivot key>, <list query selecting the qualified parent key>)` and `count(distinct <that key>)`, grouped by `<related table.column>`. Constraints written into the relation method (`where()`, `wherePivot()`) and the related model's global scopes stay.
- No `ORDER BY`: `FacetReadModel` sorts the buckets (null first), because PostgreSQL sorts NULL last.
- `Facets\Exceptions\FacetNotCountable`: unknown relation; a relation that is not HasMany/BelongsToMany (MorphToMany explicitly — without constraints it would lose its morph type); a related model on another connection (compared via `getConnection()->getName()`, since `getConnectionName()` is null for the default); a non-scalar value from the database.

**Trap — the scope rule.** The whole security property of the counter is that `$scope` is applied in full to every facet and only `$requested` is reduced. Keep the two apart from the request down to the read query:

- Merge them first and pass the result as `$requested`, and a facet on `branch_id` drops the branch scope together with the client's branch filter: counts across tenants.
- Pass the merged filter as `$scope`, and the facet's own client filter is never dropped: every tab shows the count of the selected one.
- Never call `without()` on a scope.

**Trap — joins.** A column facet counts `count(*)` on the list query. A base query that joins a to-many table counts a row once per joined row. Relation facets are not affected (`count(distinct …)`).

### Search

- `Models\Filter\Methods\TokenSearchFilterMethod` — abstract, `implements FilterMethod`, `use CustomFilter`. Constructor `(protected readonly string|int $value)`, named `$value` because `PendingFilter::createFilter()` passes the filter data by constructor parameter name. Subclasses implement `type()` and `columns(): list<string>`, and may override `mode(): SearchMode` (default `Prefix`) and `maxTokens(): int` (default 5). `Filter\Enums\SearchMode { Prefix, Contains }`.
- `format()` overrides the trait's `[]`: `value` must be present and an int or a UTF-8 string, else `MalformedFilterFormatException` instead of a `TypeError` in the constructor. `''` passes (Laravel skips non-implicit rules on empty strings) and adds no constraint.
- Tokens: `preg_split('/\s+/u', …, PREG_SPLIT_NO_EMPTY)` (Unicode whitespace, NBSP included), de-duplicated by `mb_strtolower()` with the first occurrence kept, then `array_slice(…, 0, maxTokens())`.
- SQL: one nested `where (… or …)` per token. Own columns are `orWhereRaw('<wrapped qualified column> like ? escape \'!\'')`. Relation columns are grouped by relation (split at the last dot) into one `orWhereHas()` per relation, with the ORs nested inside. Escaping is a single-pass `strtr` (`!` → `!!`, `%` → `!%`, `_` → `!_`).
- Why raw: `where($column, 'like', …)` and Laravel's `whereLike()` emit no ESCAPE clause, and the implicit escape differs (`\` on MySQL unless `NO_BACKSLASH_ESCAPES`, none on SQLite).
- Register the type in `eloquent-filtering.custom_filters` only, **not** in `laravel-ddd.filters.custom_types`. The request adds it from a dedicated `search` parameter with `Builder::custom()` (`AcceptsSearch` does this), so a client cannot send it through `filters`.
- Case sensitivity is the collation's business. The `testing` connection is SQLite, which folds ASCII only: tests can prove byte-exact umlaut matching, not `Ü`/`ü` folding or `ß` = `ss`.

## List requests and sorting

The HTTP side of a read-model list: what turns `per_page`, `cursor`, `filters`, `sort`, `search` and `facets[]` into a page size, a `Sort`, a `Filter\Builder` and a list of facets, and the read-time errors into 422. It lives in `Infrastructure` because it uses `Filter\Builder`, Eloquent and `eloquent-filtering`; only `FacetsResource` is in `Application\Resources` (it depends on the Domain read models alone).

### Sort

- `Models\Sort\Sort` — `final readonly`, private constructor. `byKey($key)` (no fields) and `fromArray(array $fields, list<string> $allowed, string $key, list<string> $unique = [])` (`'-created_at'` = descending). `fields()` are the requested fields, `orders()` the applied order, `cursorParameters()` the column names of `orders()`.
- `orders()`: the fields up to the first unique one (the key or a field in `$unique`), which ends the order; without one, all fields and then the key **in the direction of the last field** (ascending without fields). Both are for the index: an index on `(field)` carries the key ascending, so a backward scan gives `field DESC, key DESC`, never `field DESC, key ASC`; and MariaDB does not serve `ORDER BY email, id` from a unique index on `email` (measured in waz-api, GINI-308: filesort on every page, seconds on 1M rows).
- `fromArray()` throws `Sort\Exceptions\SortNotValid`: `malformedField` (not a string, `''`, `'-'`), `unknownField`, `duplicateField` (also `x` with `-x`).
- `apply(EloquentBuilder)` calls `reorder()` and then `orderBy()` per order with the **unqualified** column, so the cursor parameters are named exactly as `cursorParameters()` says and the cursor rule can check them before the query runs. `@template TModel of Model`, returns the same builder.
- `SortField(column, SortDirection = Ascending)`, `toString()` gives the request form. `Sort\Enums\SortDirection` is backed by `asc` / `desc`.
- Relation columns are not sortable yet. They would need a join, a qualified order column and the joined value selected under its own name, which becomes the cursor parameter (`ReadModelCursorPaginator` already reads parameters from model attributes).

### Request blocks

Traits in `Http\Requests\Concerns`, each with `@phpstan-require-extends FormRequest`: `PaginatesByCursor`, `AcceptsFilters`, `AcceptsSort`, `AcceptsSearch`, `AcceptsFacets` (`@template TFacet of BackedEnum`). Each has a `…Rules()` method the request spreads into `rules()`; the doc comments on the rule items are for Scramble. Limits are methods (`defaultPerPage()`, `maxPerPage()`, `maxSearchLength()`), because a class cannot redefine a constant it got from a trait.

- No hooks: no trait defines `after()`, `withValidator()` or `prepareForValidation()` (the last is used by many requests in waz-api). Everything is a rule: `Http\Rules\ValidCursor`, `ValidFilters`, `DistinctSortFields`.
- `AcceptsFilters` overrides `validationData()` and decodes a JSON `filters` there, so request rules on `filters.*` (B2B: the tenant scope target) also see JSON. A non-blank string wins over an array between `getInputSource()` and `query`, because `input()` would let the body win whatever its form. The JSON is decoded twice (objects first) to tell a JSON list from a JSON object.
- `ValidFilters` runs `Builder::fromArray($rows)->toArray()`: `toArray()` is where the built-in filters check their values (`FilterValueNotValid`).
- `ValidCursor($parameters)` wants exactly the expected parameters (sorted comparison) and scalar values; `null` only checks that the cursor decodes. The cursor block passes `null` when the sort is invalid, so only the sort rule reports.
- Seams, detected with `method_exists()` on private, distinctly named methods: `ProvidesFilters::filters()` (used by `AcceptsFilters` and `AcceptsSearch`, PHP imports the identical trait method once) calls `clientFilterRows()` and `withSearchFilter()`; `PaginatesByCursor` calls `sortCursorParameters()` of `AcceptsSort`. PHPStan does not analyse the traits in this package (no class in `src` uses them) and does not report errors in vendor files for consumers, so the tests are their only check.
- Blank input: Laravel skips non-implicit rules on `''`, and `ConvertEmptyStringsToNull` turns it into `null` before the rules see it over HTTP. So the parameters are `nullable` (a blank `sort=` counts as missing either way), the elements `sort.*`, `facets.*` and `filters.*` are `filled` (implicit, so a blank element fails), and `perPage()`, `sort()` and `facets()` check `filled()`. Not `required` on the elements: Scramble marks the parameter required for it. `ListRequests::resolve()` skips the middleware, so the blank cases are tested over HTTP too (`BlankListParametersTest`).
- `AcceptsSort::uniqueSortableFields()` (default `[]`) feeds `$unique` of the sort.

### Errors and facets

- `Http\ClientFilterErrors::translate(Closure $read, string $attribute = 'filters')` turns any `FilterException` of `eloquent-filtering` (e.g. `DeniedFilterException` from `allowedFilters()`) into `ValidationException::withMessages()`; everything else passes unchanged. Reads only.
- `Application\Resources\FacetsResource` renders `Collection<int, FacetReadModel>` as `{key: [{value, count}]}`; the `@return` array shape of `toArray()` is the schema of the `FacetsResource` component. `addTo($collection, $facets, Closure $count)` returns the collection untouched for `[]` (no query), otherwise merges `meta.facets` into the existing `additional` with `array_replace_recursive()`. Scramble cannot see `meta.facets` through `addTo()` on its own, see below.

### Scramble

`dedoc/scramble` is a dev dependency and a `suggest`. `Infrastructure\Scramble\ScrambleExtensions::register()` runs from `LaravelDddServiceProvider` once the app has booted, and only if Scramble is installed and its provider loaded:

- `FacetsResourceAddToExtension` (`StaticMethodReturnTypeExtension`): types `FacetsResource::addTo()` as the collection or the collection with `additional(['meta' => ['facets' => FacetsResource]])`, so the 200 response is `anyOf` the page without and with `meta.facets`. Scramble reads a class in `vendor/` from its signature and PHPDoc only, and `@return TCollection` loses the `additional` data.
- `SortParameterSchema`, `FiltersParameterSchema` (`AllRulesSchemasTransformer`). Scramble keeps rule transformers per API, unlike the global `registerExtension()`, so `register()` adds them with `withRuleTransformers()` to every API in `getConfigurationsInstance()->all()`. That includes the APIs a consumer registers in its own `boot()`, which runs before the `booted` callback (1.4.0 added them to the default API only, and waz-api's two APIs missed them). An API registered later is cloned from the default one and has them. `RuleTransformers::all()` drops duplicate classes, so an API with them added by hand runs each once, and both transformers are idempotent anyway. Scramble drops a parameter whose `x.*` has rules, and with it its doc comment, and builds `sort[]` from `sort.*` with the enum copied onto the array. They run on `DistinctSortFields` and `ValidFilters`, the rules only `sort` and `filters` have: `sort[]` becomes an array of the `sort.*` schema (`uniqueItems`, not nullable), `filters` `anyOf` a JSON string and a list of `{type, target, value}`, with every `filters.*` rule removed, those of the request too. `value` is any value with a description: an empty schema is `{}` only until someone decodes the JSON to PHP arrays and encodes it again, then it is `[]`, which is no valid schema.

These use Scramble's internal types (0.13, pre-1.0). CI runs `composer update`, so a Scramble release that breaks them fails `ListEndpointSpecTest`. That test reads the package classes through reflection (`PackageClasses` matcher), as in a consumer; without it, Scramble analyses their code and documents something a consumer never gets.

**Traps.**

- **A request that overrides `validationData()`** must return `$this->withDecodedFilters(...)`. Otherwise its rules on `filters.*` never see JSON filters; `filters()` then throws a `LogicException` instead of handing them out.
- **The search type** goes into `eloquent-filtering.custom_filters` only, never into `laravel-ddd.filters.custom_types`. `filters()` throws a `LogicException` if it is there.
- **The key of the request and the read query must match.** With `AcceptsSort` the read query takes it from `Sort::key()`. Without it, the read query orders by its own key, and a different `listKey()` makes the cursor rule reject every cursor from page 2 on.
- **Sort columns must not hold NULL** (`CursorValueIsNull`), and a datetime sort column must store whole seconds: the cursor carries the cast value as `Y-m-d H:i:s`, so fractions repeat rows ascending and skip rows descending (`SortPaginationTest` pins this).
- **A unique sort field must be unique.** The order ends at it without the key, so the next page skips every row that ties with the cursor row.

Tests: `tests/Foundation/Infrastructure/Http/**`, `tests/Foundation/Infrastructure/Models/Sort/**` (`SortPaginationTest` pages the `testing` DB), `tests/Foundation/Infrastructure/Scramble/**` (the generated document), `tests/Foundation/Application/Resources/FacetsResourceTest.php`; fixtures in `tests/Fixtures/Infrastructure/Http/Requests` (`ListRequests::resolve()` resolves a request through the container, which validates it, without the HTTP middleware), `tests/Fixtures/Infrastructure/Scramble` and `SortTestItemModel`.

## Exceptions

| Namespace | Class |
| --- | --- |
| `Infrastructure\Exceptions` | `CantSaveModel`, `CantDeleteModel`, `CantDeleteRelatedModel`, `ConcurrencyException` |
| `Infrastructure\Mappers\Exceptions` | `NoMapperFoundForEntity` |
| `Infrastructure\Models\Exceptions` | `EntityClassNotAvailable` |
| `Infrastructure\Models\Facets\Exceptions` | `FacetNotCountable` |
| `Infrastructure\Models\Filter\Filters\Exceptions` | `FilterArrayNotValid`, `FilterValueNotValid`, `CustomFilterNotValid` |
| `Infrastructure\Models\Sort\Exceptions` | `SortNotValid` |
| `Infrastructure\Pagination\Exceptions` | `CursorValueIsNull` (`LogicException`) |
| `Infrastructure\Repositories\Exceptions` | `EntityClassNotAvailable` |

The two `EntityClassNotAvailable` classes are distinct — check the namespace when catching or asserting.
