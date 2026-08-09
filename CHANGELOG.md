# Changelog

All notable changes to `laravel-ddd` will be documented in this file.

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
