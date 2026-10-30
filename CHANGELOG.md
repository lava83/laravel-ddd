# Changelog

All notable changes to `laravel-ddd` will be documented in this file.

## v1.2.0 - 2026-10-30

This release adds free-text search and facet counts for cursor-paginated read-model lists. Both run on the relational database through the existing read side (`Model::filter()` and `ReadModelCursorPaginator`), with no search engine and no new dependency. It is backwards compatible; existing code needs no changes.

### Added

#### Facet counts

`FacetCounter::count()` counts per value of one attribute of a list, for example the status tabs of an admin UI. It uses the same base query the list uses for its page (#50).

```php
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;

$facets = FacetCounter::count(
    fn (Builder $filter) => $this->baseQuery($filter),
    $scope,      // what the caller may see (tenant, branch), applied in full
    $requested,  // the client's filters; each facet drops its own
    FacetDefinition::column('status', 'status_id'),
    FacetDefinition::relation('services', 'services', 'id'),
);

$facets->first()->toArray();
// [['value' => null, 'count' => 3], ['value' => 1, 'count' => 120], ['value' => 2, 'count' => 7]]

```
- `FacetDefinition::column()` counts the rows per value of a column of the list model. `FacetDefinition::relation()` counts the distinct rows per value of a column of a `HasMany` or `BelongsToMany` relation on the same connection.
- Each facet keeps the whole scope and every requested filter except its own. Pass the scope and the client's filters separately: the scope is never reduced.
- The results are `Domain\ReadModels\Facets\FacetReadModel`s holding `FacetBucketReadModel`s, ordered by value with the null bucket first. A value without rows has no bucket, so a missing value means 0.

#### Search

`TokenSearchFilterMethod` is an abstract eloquent-filtering custom filter for a search over several columns, columns of a relation included:

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
        return ['email', 'data.firstname', 'data.lastname'];
    }
}

```
Every token of the phrase must match, in any of the columns. Matching uses `SearchMode::Prefix` (the default) or `SearchMode::Contains`, with at most `maxTokens()` tokens (default 5). `%`, `_` and `!` are literals on MySQL/MariaDB, SQLite and PostgreSQL alike.

#### Removing client filters

- **`Filter\Builder::without(string ...$targets)`** returns a new builder without the filters on the given targets. Use it on client filters only, never on a scope.

### Upgrade notes

No action required. To use the search, register the filter in `eloquent-filtering.custom_filters` and allow it on the model. The README recommends not listing it in `laravel-ddd.filters.custom_types`, and setting it from a dedicated `search` parameter instead.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-10-28

This release fixes the id carried by domain events of integer-keyed aggregates and adds `DomainEvent::withAggregateId()`, which the fix is built on. It is backwards compatible; existing code needs no changes.

### Fixed

#### Domain events carry the persisted id of integer-keyed aggregates

Events recorded before the first save of an aggregate with an `Integer` identity (typically a `…Created` event recorded in a `create()` factory, while `id()` is still `…Id::new()`) were dispatched with the placeholder id `0`. Only the aggregate itself received the key the database assigned, so a listener could not reference the created aggregate by its id (#46).

`Aggregate::idFromPersistence()` now moves every buffered event recorded against the previous id onto the new one. Both save paths reach it through `hydrate()`, so `Repository::saveEntity()` and `ApiRepository::persistCommands()` are fixed alike.

```php
$counter = Counter::create('one');      // records CounterCreated with id 0
$repository->save($counter);

// Listeners now receive CounterCreated with aggregateId() === $counter->id(), e.g. 1


```
`Uuid` identities were not affected: their id is known before the event is recorded.

### Added

- **`DomainEvent::withAggregateId(Id $aggregateId): static`** returns a copy of the event for another identity of the same aggregate. Payload, name, version and `occurredOn` are kept.

### Upgrade notes

No action required.

- A workaround that re-records the creation event with the real id after the insert (for example `clearEventsOfType()` plus `recordEvent()` in an `idFromPersistence()` override) is no longer needed and can be removed.
- Events that implement the `Domain\Contracts\DomainEvent` interface without extending `Domain\Events\DomainEvent` have no `withAggregateId()` and still keep the old id. The interface gains the method in 2.0 (#47).

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v1.0.0...v1.1.0

## v1.0.0 - 2026-10-20

First stable release. From here on the package follows semantic versioning: breaking changes only in a new major version. The domain layer was tightened on the way, so **this release contains breaking changes for value objects, identities and domain events**; the upgrade notes below list every one of them.

`Entity::fromState()` and the `Infrastructure\Models\Model` it receives are a permanent, stable exception to the layering rules and will not be removed.

### Changed

#### Every value object validates itself

`ValueObject` now works like `Entity`: its constructor calls the abstract, protected `validate(): array` and throws a `ValidationException` (built with `ValidationException::fromArray()`) when it returns errors. The errors are keyed by property name.

```php
final class Title extends ValueObject
{
    private function __construct(private readonly string $value)
    {
        parent::__construct();
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
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
All 13 built-in value objects use it. An architecture test checks that every concrete value object of the package calls `parent::__construct()` and keeps `validate()` out of its public API.

#### Immutable timestamps in `Entity`

`Entity` no longer touches Laravel's mutable `Carbon`; timestamps are `CarbonImmutable` and the architecture test forbids `Carbon` in the domain. `updatedAt()` returns `createdAt()` until the entity is updated for the first time.

#### Typed domain events

`DomainEvent`, `AggregateRoot` and `Aggregate` carry a `TId` template, so `aggregateId()` is typed. The `DomainEvent` payload may be a `Collection` or an array and is copied into a new `Collection` on the way in and out, so neither the caller nor a reader can change an event. `updateEntity()` and `updateAggregateRoot()` accept `Collection|array` for `$changes` as well.

### Fixed

- **`Id::equals()`** compares the class before the value: `AccountId(1)` no longer equals `B2cUserId(1)`.
  
- **`Money`:** `__toString()` keeps the decimals (`12.50 €`, was `12 €`), `isEqual()` compares cents instead of floats (`0.1 + 0.2` equals `0.3`).
  
- **`Email`:**
  
  - `isCompanyEmail()` and `isGermanProvider()` agree on the providers (`posteo.de`, `mailbox.org` are personal).
  - `isRoleBased()` and `isValidForNotifications()` match whole words (`info.team`, `info-berlin` and `support2` match, `italy` and `bouncer` do not); `bounces` is blocked as well.
  - The blocked domain list is no longer case sensitive.
  - `domainWithoutSubdomain()` knows two-label public suffixes (`example.co.uk`, `example.com.au`, from a curated list, not the full public suffix list).
  - `yahoo.co.uk` and `hotmail.co.uk` count as personal providers.
  
- **`DateRange`:** `singleDay()` no longer mutates its argument, `fromString()` reports why a range is rejected, `businessDays()` and `businessDates()` count the last day, `isBusinessWeek()` is only true for Monday to Friday of one week.
  
- **`Link`:** a URL without a path is accepted, port and userinfo stay in the value.
  
- **`Phonenumber`:** the value is E.164 (US: `+16502530000`), country codes outside `CountryAreaCode` throw a `ValidationException` instead of a `ValueError`.
  
- **`Json`:** `remove()` removes the key (it was a no-op), scalars and `null` are rejected with a `ValidationException`, empty objects stay `{}` through `set()`, `merge()`, `remove()` and `snakeCaseKeys()`, `fromArray([])` is `{}`.
  

Integers beyond the float range (about 2^53) lose precision in `Json`; PHP's `json_decode()` cannot avoid that.

### Upgrade notes

**Own value objects** (this affects every one of them):

- Implement `protected function validate(): array` (messages keyed by property name, `[]` when valid).
- Call `parent::__construct()` in the constructor, after assigning the properties `validate()` reads and before deriving anything from them. Forgetting it silently skips validation.

**Identities:**

- The `Id` hook `validate($value): void` is replaced by `validate(): array`, which reads `$this->value`.
- `Id::equals()` is `false` for an identity of another class, even with the same value.
- `MongoObjectId::equals()` takes an `Id`; anything else raises a `TypeError`.

**Exceptions and error keys:**

- `Color` (was Laravel's `ValidationException`), `Money` and `Integer` (were `InvalidArgumentException`) throw the domain `ValidationException`.
- Error keys are property names: `value` (`Email`, `Color`, `Link`, `Phonenumber`, `Json`, `Id`), `amount` and `currency` (`Money`), `startDate` and `endDate` (`DateRange`), `ids` (`Sqid`).

**Domain events:**

- Subclasses of `DomainEvent` need `@extends DomainEvent<TheId>`, custom `AggregateRoot` implementations `@implements AggregateRoot<TheId>`. With PHPStan level 6 or higher the missing generics are reported otherwise. Runtime behaviour does not change.

**Behaviour changes to check in your code:**

- `Money::__toString()` returns `12.50 €`; `isEqual()` treats amounts that differ by less than half a cent as equal.
- `DateRange::businessDays()` and `businessDates()` include the last day, so counts can grow by one. `isBusinessWeek()` is only true for five days, so `previousPeriod()` and `nextPeriod()` of a longer Monday to Friday range now shift by its length.
- `Phonenumber::value()` is E.164; `localAreaCode` and `number` hold digits only. Unsupported country codes throw.
- `Link::value()` contains port and userinfo.
- `Json::jsonSerialize()` returns `mixed` (the document as `json_decode()` reads it); `json_encode()` output is unchanged except that empty objects stay `{}`. `toArray()` and `data()` still return plain arrays.
- `Email`: addresses on the blocked domain list are rejected whatever their case, and the provider and role heuristics changed as described above.

### Internal

- CI runs the tests in the QA workflow on PHP 8.4 and 8.5 and fails below 90 % coverage on PHP 8.4 (currently 95.7 %).
- The test suite grew from 482 to 866 tests, covering every building block of the package.
- `composer.json`: the description says Laravel 13, `minimum-stability: dev` is gone, the autoload entry for the missing `database/factories/` is removed.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.8.1...v1.0.0

## v0.8.1 - 2026-10-17

This release adds the consuming side of cursor pagination: reading a paginated list from an API and serving it on as a regular cursor page. It is additive; existing code needs no changes.

### Added

#### Cursor pages read from an API

`ReadModelCursorPaginator` serves the producing side, starting from an Eloquent query. An application that reads a list through `ApiClient` has no query and no model, only `data`, `meta` and cursor tokens it must not interpret. Rebuilding the other API's ordering and token format would break as soon as that API changes its sort. The cursors are therefore handed on unchanged.

```php
use Lava83\LaravelDdd\Infrastructure\Pagination\ApiCursorPageMapper;

$pages = new ApiCursorPageMapper;

$response = $api->get('v1/accounts', $pages->query(
    filter: ['status' => 'active'],
    perPage: 50,
    cursor: $request->query('cursor'),
));

return $pages->fromResponse(
    $response,
    path: '/accounts',
    mapItem: fn (array $payload) => AccountReadModel::fromArray($payload),
);




```
- **`RelayedCursorPaginator`:** a `CursorPaginator` subclass built from items, page size and the `next` / `prev` tokens of the neighbouring pages. Nothing is derived from the items, so read models need no sort column. `Resource::collection($page)` produces `data`, `links` and `meta` as usual, with your own URLs.
- **`RelayedCursor`:** keeps the token exactly as received. Laravel's `Cursor::encode()` rebuilds a token from decoded parameters, which is not guaranteed to give the same string back.
- **`ApiCursorPageMapper`:** reads `data` and `meta.{per_page,next_cursor,prev_cursor}` from an `Illuminate\Http\Client\Response` and builds the request query (`per_page`, `cursor`, `filter`, names configurable, unset values left out).
- **Loud failures:** an answer without `data`, `meta`, an integer `meta.per_page` or the two cursor keys, or with a token that is not a Laravel cursor, throws `ApiException`. A broken answer never reads as "last page, no results". An empty cursor string counts as no cursor.
- **Own links:** the page links use the path you pass in, so URLs of the API never leak into your responses. The request query stays in the links.

The mapper expects the shape a Laravel resource collection produces. An API with another shape needs a small mapper of its own that feeds `RelayedCursorPaginator`, which knows no token format.

### Upgrade notes

No action required. Nothing is bound in the container, and `ReadModelCursorPaginator` is unchanged. Applications that built a page value object for API lists themselves can replace it with `ApiCursorPageMapper` and return `Resource::collection($page)` directly.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.8.0...v0.8.1

## v0.8.0 - 2026-10-17

This release adds cursor pagination for read models and opt-in support for aggregates that live behind an HTTP API, and fixes `Entity::hydrate()` for models that do not carry every attribute. It is additive; existing database-backed code needs no changes.

### Added

#### Cursor pagination for read models

New `Infrastructure\Pagination\ReadModelCursorPaginator`, a `CursorPaginator` subclass for pages whose items are read models.

Laravel reads `next_cursor` / `prev_cursor` from the page items under the ORDER BY column name. After mapping a page to read models with `through()`, this fails with `Undefined property` as soon as the sort column (`account_id`) is not a property of the read model (`id`). A SELECT alias does not help, because the cursor `WHERE` clause cannot use one.

```php
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;

return ReadModelCursorPaginator::fromPage(
    $this->baseQuery($filter)->orderBy('account_id')->cursorPaginate($perPage),
    fn (AccountModel $m) => AccountReadModelMapper::fromAccountModel($m),
);





```
- **Source tracking:** each read model keeps its source model, and the cursor values are read from there. The domain stays free of persistence knowledge.
- **Compatible:** still an `Illuminate\Pagination\CursorPaginator`, so return types and Scramble keep working.
- **Robust:** works with `filter()` / reordering of the collection and with `JsonResource` wrappers.
- **Unchanged Laravel behaviour:** query parameters stay in `links`; a cursor that does not match the ordering still throws `UnexpectedValueException`.
- **Naming:** the factory is called `fromPage`, not `map`. A static `map()` would shadow the paginator's collection forwarding, which Laravel uses when serialising resource collections.

#### API-backed aggregates (opt-in)

Aggregates that live behind an HTTP API instead of a table. Nothing is bound by the package, so pure database users do not notice it.

- **`ApiRepository`:** reads wrap `data` payloads in a `RemoteModel`. Writes are command-based, not diff-based: `commands()` maps each `DomainEvent` to the API call that realises it. All events must be mapped, otherwise `UnmappedDomainEvent` is thrown **before the first call**. Calls run in recording order, the aggregate is hydrated from the last response, and events are published afterwards. The calls are **not atomic**: if one fails, earlier calls have already happened and the events stay on the aggregate.
- **`RemoteModel`:** attribute bag for `fromState()` / `hydrate()`; `save()`, `delete()` and `newQuery()` throw a `LogicException`.
- **`ApiClient` with `ApiClientConfig`:** `get/post/patch/delete`, timeouts, and a status to `ApiException` hierarchy (`Unauthorized`, `Forbidden`, `NotFound`, `ValidationFailed`, `Unavailable`). Only GETs are retried (connection errors and 5xx); writes never are, there are no idempotency keys.
- **Authentication via `ApiAuthenticator`:** `ClientCredentialsAuthenticator` (token cached as a plain string, no `cache.serializable_classes` entry needed) and `StaticBearerAuthenticator`. An authenticator that also implements `RefreshableAuthenticator` gets one repeat after a 401; fixed credentials get none.
- **Loud failures:** responses without a `data` payload and token responses without an `access_token` throw instead of reading as "no results".

The host builds the `ApiClient` in its own service provider, so several APIs can live side by side. See "API-backed aggregates" in the README.

### Fixed

- **`Entity::hydrate()`** no longer crashes on models that do not carry `created_at`, and no longer casts a missing `version` to `0`. The base `Model` casts these columns, so `hasAttribute()` reported them as present even when the source never carried them (an API payload, a partial select). Absent values now leave the entity untouched. `persistedVersion` still comes from the model, so optimistic locking via `updateWithVersionGuard()` is unaffected.

### Upgrade notes

- **`Entity::hydrate()`:** a model that carries no `version` used to yield `persistedVersion() === 0` (and a guaranteed `ConcurrencyException` on update). It now keeps the entity default of `1`. Models loaded from a table with a `version` column are unaffected. If you select columns partially, include `version` before saving.
- **Paginating read models:** replace `->through(...)` on a cursor-paginated page with `ReadModelCursorPaginator::fromPage($page, $mapper)`. If you already have a local copy of this class (e.g. with a static `map()`), switch to the package class and rename the call to `fromPage()`. The return type stays `Illuminate\Pagination\CursorPaginator`.
- **Migrating a project-local API layer to the package:**
  - Replace the local `ApiRepository`, `RemoteModel`, `UnmappedDomainEvent`, HTTP exceptions and token DTO with the package classes (`Infrastructure\Repositories\ApiRepository`, `Infrastructure\Models\RemoteModel`, `Infrastructure\Api\Exceptions\*`).
  - Build the client from `ApiClientConfig` plus an authenticator and alias it as `Lava83\LaravelDdd\Infrastructure\Api\ApiClient`, otherwise the container cannot resolve the repositories (`ApiRepository` type-hints `ApiClient`). A thin subclass of `ApiClient` is fine for the project's own type hints.
  - Pass the token endpoint as `tokenPath` to `ClientCredentialsAuthenticator` (default `oauth/token`).
  - **Do not reuse an old cache key** that still holds a serialized token DTO: the package caches the access token as a plain string and would fail on the stale entry. Leave `cacheKey` unset (the default is derived from base URL and client id) or flush the old key on deploy. The `cache.serializable_classes` entry for the DTO can go.
  - Update `catch` blocks to the new exception classes (`NotFound`, `Forbidden`, `Unauthorized`, `ValidationFailed`, `Unavailable`, base `ApiException`), and any tests that assert on exception messages: they now read `API …` instead of a vendor-specific prefix.
  

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.7.0...v0.8.0

## v0.7.0 - 2026-09-30

### ⚠️ Breaking changes

#### `Entity::validate()` now returns an error bag keyed by property

`validate()` no longer returns a flat `list<string>`. It returns `array<string, list<string>>`: messages keyed by the name of the property they belong to.

```php
// Before
public function validate(): array
{
    return $this->scopes->isEmpty() ? ['Scopes must not be empty'] : [];
}

// After
/**
 * @return array<string, list<string>>
 */
public function validate(): array
{
    return $this->scopes->isEmpty() ? ['scopes' => ['Scopes must not be empty']] : [];
}






```
`isValid()` is unchanged (`validate() === []`).

**Migration:** update every `validate()` override in your entities and aggregates to return the keyed shape. PHP cannot enforce the array shape at runtime, so an override that still returns a plain list will not fail, but its messages end up under integer keys and are useless as field errors. PHPStan will flag it in your code once the return type is declared. The `make:aggregate` stub has been updated accordingly.

#### `ValidationException` carries the error bag

- New `ValidationException::errors(): array<string, list<string>>` returns the bag.
- `ValidationException::fromArray()` now expects the keyed shape and keeps it. `getMessage()` still returns all messages joined with a space, so existing message assertions and `ErrorResource` output are unchanged.
- An exception created directly (`new ValidationException('…')`, as the Value Objects do) has an empty bag: `errors() === []`.

Turning the bag into a `422` with field errors is left to the application, e.g. in your exception handler:

```php
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException as DomainValidationException;

$exceptions->render(function (DomainValidationException $e) {
    return $e->errors() === []
        ? null
        : throw LaravelValidationException::withMessages($e->errors());
});






```
### 🐛 Behaviour changes

#### Updates now enforce invariants and are atomic

Previously only the constructor validated an entity, so an update could leave it in an invalid state without any error.

`Entity::updateEntity()` now runs `validate()` after applying the changes:

- If an invariant is violated, the `old_*` values are written back, `dirty()` is cleared and a `ValidationException` is thrown. Value, `version()` and `updatedAt()` stay exactly as they were.
- `version` and `updatedAt` are only bumped after the check passes.
- This applies to every aggregate automatically, including changes made through `updateAggregateRoot()`. A rejected change records **no** domain event.
- `idFromPersistence()` goes through the same check. For an entity that was valid before, this has no effect.

**Migration:** code that relied on invalid intermediate states being accepted (e.g. building up an entity through several partial updates) will now throw. Either validate only the final state, or apply the related changes in a single `updateEntity()` / `updateAggregateRoot()` call. Guards you added in the application layer to check invariants after an update (`ensureValid()` and similar) can be removed.

#### Known limitation

The `$eventClass` check in `updateAggregateRoot()` still runs after the change was applied. An invalid event class therefore throws a `LogicException` with the state already changed. This is unrelated to the validation above and unchanged.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.14...v0.7.0

## v0.6.14 - 2026-09-29

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.13...v0.6.14

## v0.6.13 - 2026-09-14

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.12...v0.6.13

## v0.6.12 - 2026-09-13

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.11...v0.6.12

## v0.6.11 - 2026-09-02

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.10...v0.6.11

## v0.6.10 - 2026-09-01

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.9...v0.6.10

## v0.6.9 - 2026-09-01

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.8...v0.6.9

## v0.6.8 - 2026-09-01

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.7...v0.6.8

## v0.6.7 - 2026-08-31

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.6...v0.6.7

## v0.6.6 - 2026-08-27

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.5...v0.6.6

## v0.6.5 - 2026-08-23

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.4...v0.6.5

## v0.6.4 - 2026-08-22

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.3...v0.6.4

## v0.6.3 - 2026-08-22

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.2...v0.6.3

## v0.6.2 - 2026-08-12

### What's Changed

* Adds ReadModel and JsonResource for 201 responses by @lava83 in https://github.com/lava83/laravel-ddd/pull/27

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.6.0...v0.6.2

## v0.6.1 - 2026-08-10

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.14...v0.6.1

## v0.6.0 - 2026-08-09

### v0.6.0 - 2026-08-09

A new `make:aggregate` Artisan command scaffolds the core DDD building blocks for a bounded context in one step — the aggregate root, its identity Value Object, and an Eloquent model — with optional repository and entity-mapper generation. The package now also ships a publishable config file that controls the root namespace and how bounded contexts map onto the layer namespaces.

#### Added

- **`make:aggregate` — scaffold an aggregate and its supporting classes.** Given an aggregate name and a bounded context, the command always generates three files and can generate three more on request:
  
  Always:
  
  - the **identity Value Object** `{Name}Id` (in `Domain\…\ValueObjects\Identity`), extending the package's `Uuid` or `Integer` base;
  - the **aggregate root** `{Name}` (in `Domain\…\Aggregates`), typed `@extends Aggregate<{Name}Model, {Name}Id>`, with `create()` / `fromState()` factory methods and a `validate()` hook;
  - the **Eloquent model** `{Name}Model` (in `Infrastructure\…\Models`), extending `Lava83\LaravelDdd\Infrastructure\Models\Model`, with a `#[Table]` attribute derived from the snake-cased plural name and a `#[Fillable('name')]` placeholder.
  
  Optional:
  
  - `--with-repository` adds the **repository contract** `{Name}RepositoryContract` (`findAll`, `findOrFail`, `save`, `remove`) and the **Eloquent implementation** `Eloquent{Name}Repository`, with `save`/`remove` wrapped in a connection-aware transaction;
  - `--with-entity-mapper` adds the **entity mapper** `{Name}Mapper` (`@extends EntityMapper<{Name}, {Name}Model>`) with `toEntity()` / `toModel()` stubs to fill in.
  
- **Choice of identity type — UUID v7 or auto-increment integer.** Pass `--id-type=uuid` (default) or `--id-type=integer`, or pick from the prompt. UUID identities generate a `readonly` id via `{Name}Id::generate()` and add the `HasUuids` trait to the model; integer identities use `{Name}Id::new()`. An invalid `--id-type` fails the command with a clear message.
  
- **Interactive prompts with non-interactive fallback.** Run it bare and it prompts for the aggregate name, bounded context, id type, and whether to also create a repository and mapper (both default to yes). In non-interactive contexts (e.g. CI) it takes everything from arguments and options, defaults the id type to `uuid`, and only scaffolds the repository/mapper when their flags are present.
  
- **Layer-aware namespacing, driven by config.** Namespaces are built from the new `bounded_contexts_root_namespace` and honour `bounded_contexts_without_own_layers`:
  
  - `true` (default) — shared layers: `{Root}\{Layer}\{BoundedContext}\{Sub}`
  - `false` — each context owns its layers: `{Root}\{BoundedContext}\{Layer}\{Sub}`
  
  Target file paths are resolved from your `composer.json` PSR-4 map (longest-prefix match across `autoload` and `autoload-dev`), falling back to a top-level directory named after the root namespace.
  
- **Publishable config file `config/laravel-ddd.php`.** The service provider now registers the config file and the command. Two keys, both env-overridable:
  
  - `bounded_contexts_root_namespace` — default `App\BoundedContexts` (env `BOUNDED_CONTEXTS_ROOT_NAMESPACE`)
  - `bounded_contexts_without_own_layers` — default `true` (env `BOUNDED_CONTEXTS_WITHOUT_OWN_LAYERS`)
  
- **Safe writes and next-step guidance.** Existing files are reported as `SKIPPED (exists)` and left untouched unless you pass `--force`; each written file is listed as `CREATED`. After scaffolding, the command warns if the root namespace isn't autoloaded yet (with the PSR-4 snippet and `composer dump-autoload` to run) and prints the service-provider bindings to register — the mapper via `entity_mapper_resolver()->registerMapper(...)` and the repository via `$this->app->bind(Contract::class, Implementation::class)`.
  

```bash
# Interactive — prompts for everything
php artisan make:aggregate

# Fully specified
php artisan make:aggregate Order OrderProcessing \
    --with-repository --with-entity-mapper --id-type=uuid





















```
The generated files are skeletons: the model and mapper carry a `name` placeholder, and the aggregate's `validate()` and the mapper's `toModel()` are left for you to fill in.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.14...v0.6.0

## v0.5.14 - 2026-08-05

The Eloquent filter `Builder` gains two additions: reconstruct a filter set from a plain array (e.g. an HTTP request payload), and merge two filter sets with explicit control over whether existing filters may be overridden. Both are backward compatible — no breaking changes.

### Added

- **`Builder::fromArray()` — rebuild a filter Builder from an array.** The inverse of `toArray()`: it reconstructs a `Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder` from the operator-array shape (`[['type' => '$eq', 'target' => …, 'value' => …], …]`), so a filter set that arrives over HTTP can be turned straight back into a Builder. Validation is strict — it throws the new `Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterArrayNotValid` on a missing key, an unknown operator, or a value whose type does not match the operator. Carry the payload as JSON rather than bracket-notation query parameters: query-string values are always strings, which the numeric (`$gt`, `$gte`, `$lt`, `$lte`) and `$null` operators reject. See the new [Filtering](README.md#filtering) section for the request example.
  
- **`Builder::merge()` — combine two filter sets with override control.** Merges another Builder into this one and returns a **new** Builder, mutating neither operand. Behaviour is governed by the new `Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\MergeStrategy` enum:
  
  - `MergeStrategy::KeepExisting` (default) — appends the incoming filters and never removes an existing one, so default filters (e.g. tenant scoping) always survive.
  - `MergeStrategy::Override` — lets an incoming filter replace existing filters that match on **both** target and operator (type).
  
  This makes it safe to layer request-supplied filters on top of protected defaults: an existing filter can only be replaced when `Override` is passed explicitly.
  

```php
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\MergeStrategy;

$defaults = Builder::make()->eq('tenant_id', $tenantId);
$incoming = Builder::fromArray($decodedRequestFilters);

// Default: the tenant scope is preserved, incoming filters are appended.
$filters = $defaults->merge($incoming);

// Opt in to replacement — only for trusted filter sources.
$filters = $defaults->merge($incoming, MergeStrategy::Override);






















```
**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.13...v0.5.14

## v0.5.13 - 2026-08-03

### v0.5.13

EntityMappers now declare their persistence model **once**, as a static property, instead of threading it through every base-helper call.

#### ⚠️ Breaking

The `string $modelClass` parameter was removed from the protected `findOrCreateModel()` and `findOrCreateModelFillData()` helpers on `EntityMapper`. Mappers that extend the base class must declare the model as a static `$modelClass` property and drop the argument:

```php
// Before
final class ArticleMapper extends BaseMapper implements EntityMapper
{
    public static function toModel(Entity $entity): ArticleModel
    {
        return self::findOrCreateModelFillData($entity, ArticleModel::class, [
            'title' => (string) $entity->title(),
        ]);
    }
}

// After
final class ArticleMapper extends BaseMapper implements EntityMapper
{
    protected static ?string $modelClass = ArticleModel::class;

    public static function toModel(Entity $entity): ArticleModel
    {
        return self::findOrCreateModelFillData($entity, [
            'title' => (string) $entity->title(),
        ]);
    }
}























```
#### Added

- A `protected static ?string $modelClass` property on `EntityMapper`. Subclasses set it once; the base helpers read it via late static binding.
- `Lava83\LaravelDdd\Infrastructure\Mappers\Exceptions\ModelClassNotDefined` (extends `RuntimeException`), thrown when `findOrCreateModel()` / `findOrCreateModelFillData()` run without `$modelClass` set.

#### Fixed

- The default-attribute merge now resolves the primary-key name from the model via `getKeyName()` instead of assuming `'id'`, so mappers backing a model with a custom primary key fill the correct key.

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.12...v0.5.13

## v0.5.12 - 2026-08-03

### What's Changed

Domain Entities can now map themselves to their persistence models, and the package's advertised core — `Entity`, `Aggregate`, and the value-object identity family — gets its first comprehensive test coverage.

#### New features

- **`Entity::toState()` — entities map themselves to models.** A Domain Entity can now convert itself into its `Infrastructure\Models\Model` via its registered `EntityMapper`, alongside a new `entityMapper()` accessor. (`1f83467`)

#### Improvements

- **Centralized `Id` validation.** The base `Id` constructor now calls a protected, overridable `validate()` method, so every identity is validated on construction regardless of which factory method built it. `Integer` and `MongoObjectId` were updated to the new signature. (`27309a1`)
- **`Uuid::fromValue()` accepts more input types** — `UuidInterface`, `int`, and `string`. (`27309a1`)

#### Architecture

- New **`MapperContracts` Deptrac layer** lets the Domain depend on `EntityMapper` contracts, via a deliberate and documented dependency cycle (`MapperContracts` ↔ `Domain` / `InfraModel`) needed to carry entity and model types in mapper signatures. (`1f83467`)

#### Testing

First comprehensive unit coverage for the core building blocks:

- **`Entity`** — construction & validation, identity/equality, timestamps & version accessors, `updateEntity()` change tracking, `hasChanged()` across CarbonImmutable/enums/VOs/nested entities, serialization & hydration. (`c58a989`)
- **`Aggregate`** — event recording, querying & buffer management, `updateAggregateRoot()` event-handling strategies, reconstitution from state, owned-child change detection, and the `private`-property serialization trap. (`14e1d27`)
- **`Uuid` and the full `Id` family** (`Id`, `Integer`, `MongoObjectId`, `Uuid`) — construction, validation, equality, serialization, prefixing, display helpers, and collection operations. New `PlainTestId`, `IntegerTestId`, and `PrefixedTestId` fixtures. (`27309a1`, `fcb3b67`)

#### Documentation

- Consolidated scattered AI-guidance files into a single `CLAUDE.md` covering layering, non-negotiables, known gaps, and testing conventions. (`c58a989`)
- Documented the `Aggregate` serialization trap for `private` subclass properties in `.claude/domain.md` (use `protected` for identity and fields). (`14e1d27`)

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.11...v0.5.12

## v0.5.11 - 2026-07-26

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.10...v0.5.11

## v0.5.10 - 2026-07-14

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.9...v0.5.10

## v0.5.9 - 2026-07-02

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.8...v0.5.9

## v0.5.8 - 2026-06-30

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.7...v0.5.8

## v0.5.7 - 2026-06-30

### What's Changed

* refactor: Unify aggregate ID types to use generic Id VO by @lava83 in https://github.com/lava83/laravel-ddd/pull/26

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.6...v0.5.7

## v0.5.6 - 2026-06-30

### What's Changed

* feat: Add toEntity helper method on models by @lava83 in https://github.com/lava83/laravel-ddd/pull/25

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.5...v0.5.6

## v0.5.5 - 2026-06-29

### What's Changed

* feat: Provide default EntityMapperResolver and bind it by @lava83 in https://github.com/lava83/laravel-ddd/pull/24

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.4...v0.5.5

## v0.5.4 - 2026-06-29

### What's Changed

* Standardizes Eloquent model usage and type hints by @lava83 in https://github.com/lava83/laravel-ddd/pull/23

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.3...v0.5.4

## v0.5.3 - 2026-06-29

### What's Changed

* Adds polymorphic reference value object by @lava83 in https://github.com/lava83/laravel-ddd/pull/21
* Adds Sqid value object by @lava83 in https://github.com/lava83/laravel-ddd/pull/22

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.2...v0.5.3

## v0.5.2 - 2026-06-29

### What's Changed

* Adds integer ID value object by @lava83 in https://github.com/lava83/laravel-ddd/pull/20

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.1...v0.5.2

## v0.5.1 - 2026-06-29

### What's Changed

* refactor: Make Entity::id() return generic Id value object by @lava83 in https://github.com/lava83/laravel-ddd/pull/19

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.5.0...v0.5.1

## v0.5.0 - 2026-06-28

### What's Changed

* Adopts PHPStan, Pint, and Deptrac for QA by @lava83 in https://github.com/lava83/laravel-ddd/pull/17

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.2.0...v0.5.0

## v0.1.4.10 - 2026-03-23

### What's Changed

* Adds Eloquent query filtering capabilities by @lava83 in https://github.com/lava83/laravel-ddd/pull/15

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.9...v0.1.4.10

## v0.1.4.9 - 2026-03-19

### What's Changed

* Extends DateRange with period navigation by @lava83 in https://github.com/lava83/laravel-ddd/pull/14

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.8...v0.1.4.9

## v0.1.4.8 - 2026-03-07

### What's Changed

* Adds float type support to entities and aggregates by @lava83 in https://github.com/lava83/laravel-ddd/pull/13

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.7...v0.1.4.8

## v0.1.4.7 - 2026-02-07

### What's Changed

* Makes country and precision nullable by @lava83 in https://github.com/lava83/laravel-ddd/pull/12

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.6...v0.1.4.7

## v0.1.4.6 - 2026-02-07

### What's Changed

* Adds GeoAddress value object by @lava83 in https://github.com/lava83/laravel-ddd/pull/11

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.5...v0.1.4.6

## v0.1.4.5 - 2026-02-06

### What's Changed

* Fixes mapper return type by @lava83 in https://github.com/lava83/laravel-ddd/pull/10

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.4...v0.1.4.5

## v0.1.4.4 - 2026-02-04

### What's Changed

* Removes start/end of day mutation on DateRange by @lava83 in https://github.com/lava83/laravel-ddd/pull/9

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.3...v0.1.4.4

## v0.1.4.3 - 2026-01-28

### What's Changed

* Makes value objects extend ValueObject by @lava83 in https://github.com/lava83/laravel-ddd/pull/8

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.2...v0.1.4.3

## v0.1.4.2 - 2026-01-28

### What's Changed

* Extends allowed types for entity values by @lava83 in https://github.com/lava83/laravel-ddd/pull/7

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4.1...v0.1.4.2

## v0.1.4.1 - 2026-01-25

### What's Changed

* Fixes entity hydration and optimistic locking by @lava83 in https://github.com/lava83/laravel-ddd/pull/6

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.4...v0.1.4.1

## v0.1.4 - 2026-01-25

### What's Changed

* Fixes entity property handling and ID comparison by @lava83 in https://github.com/lava83/laravel-ddd/pull/3
* Fix/some bugs by @lava83 in https://github.com/lava83/laravel-ddd/pull/5

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.3...v0.1.4

## v0.1.3 - 2026-01-03

**Full Changelog**: https://github.com/lava83/laravel-ddd/compare/v0.1.2...v0.1.3
