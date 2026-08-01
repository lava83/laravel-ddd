# Domain layer — `src/Domain/**`

The heart of the architecture. Read this before reviewing or scaffolding anything here.

## Hard rules

- No Eloquent, no HTTP, no database concerns. The one exception is `Entity::fromState(Model $state)` — treated as deprecated by policy (there is no `@deprecated` annotation in the source), so do not add new usages.
- Deptrac allows `Domain → AllowedPrimitives, InfraModel` only, but it cannot see classes outside its layers or any global helper. So `collect()`, `str()`, `now()`, `validator()` and arbitrary vendor imports pass a green run. Purity here is enforced in review, not by CI.
- Everything is immutable: `readonly` on all Value Object properties, `readonly` on entity identity, `readonly class` for Domain Events. No setters — a "change" returns a new instance.
- `CarbonImmutable`, never `Illuminate\Support\Carbon`. (`Entity.php` itself still violates this — known, don't fix unasked.)
- `declare(strict_types=1);` everywhere. Every property, parameter and return value typed.

## Value Objects

`ValueObject` is `abstract class ValueObject implements JsonSerializable, Stringable`. It implements nothing itself, but the two interfaces make `jsonSerialize()` and `__toString()` **mandatory** — omit either and you get a fatal error. Everything else below (`value()`, `equals()`, the static factories) is convention. Follow it anyway.

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

Conventions: private constructor, static factories (`fromString()`, `fromArray()`, `generate()`), validation inside the factory or constructor, `ValidationException` on failure (code 422 by default; `ValidationException::fromArray(array $errors)` joins messages with spaces).

Package VOs live in `ValueObjects/{Address,Business,Communication,Content,Data,Date,Identity}`. Reuse before adding.

## Identities

`Id` is the identity base: `final protected __construct(int|string|UuidInterface $value)`, plus `static fromValue()`, `static fromString()`, `equals(Id $other)`, `value()`. The constructor is `final protected` — subclasses cannot add constructor parameters.

Concrete identity types are one-liners:

```php
final class ArticleId extends Uuid {}
```

`Uuid` generates UUIDv7 via `generate()`, validates in `fromString()`, and adds prefixing (`withPrefix()`, `fromPrefixed()`), ordering (`compareTo()`, `isBefore()`, `isAfter()`) and display helpers (`shortId()`, `logId()`, `displayId()`).

`Integer` exists for persistence-generated keys. **Trap:** after insert the base repository calls `Entity::idFromPersistence()`, which routes through `updateEntity()` → `applyChanges()` → `ReflectionProperty::setValue()` on an already-initialised property. A promoted `readonly $id` throws `Error: Cannot modify readonly property` there — an `Integer`-keyed aggregate must declare its `$id` non-readonly, which trades away the identity-immutability rule. Prefer `Uuid` identities.

## Entities and Aggregates

`Entity` is `@template TModel of Model, TId of Id`. Its constructor promotes three `protected` properties, **all with defaults**:

```php
public function __construct(
    protected CarbonImmutable $createdAt = new CarbonImmutable,
    protected ?CarbonImmutable $updatedAt = null,
    protected int $version = 1,
)
```

It validates immediately: if `validate(): array<string>` returns a non-empty array, the constructor throws `ValidationException::fromArray()`.

**The base `validate()` is effectively dead.** It checks `$this->id()->value() === '' || === '0'` with strict comparison against strings, but `Uuid::value()` returns a UUID string and `Integer::value()` returns `int` — neither branch can ever be true. Real invariants must come from a subclass override.

Abstract hooks every concrete entity must implement: `id(): Id` and `static fromState(Model $state): static`.

Use `Aggregate` for aggregate roots — it extends `Entity`, implements `AggregateRoot`, and adds event recording. Note its constructor signature is `__construct(private Collection $domainEvents = new Collection)` and it calls `parent::__construct()` **with no arguments**. An aggregate therefore cannot pass `createdAt` / `updatedAt` / `version` up the chain; those only ever come back through `hydrate()`.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog;

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

    public function rename(Title $title): void
    {
        $this->updateAggregateRoot(['title' => $title], ArticleRenamed::class);
    }
}
```

State changes go through two **protected** helpers — they are internal machinery, not public API:

- `protected Entity::updateEntity(array $changes): Collection` — diffs, applies, bumps version and `updatedAt`. Returns the dirty collection, or an empty one if nothing changed.
- `protected Aggregate::updateAggregateRoot(array $changes, ?string $eventClass = null, ?DomainEvent $event = null): void` — same, plus records an event. Use this in aggregate roots.

**Gotcha:** `applyChanges()` reflects over the constructor and only writes **promoted** constructor properties. A property declared in the class body instead of the constructor signature is silently skipped, and `version`, `createdAt`, `updatedAt`, `domainEvents` are always excluded.

Change tracking keys are prefixed: `collectChanges()` writes `old_{property}` and `new_{property}` into `dirty`. `hasChanged()` special-cases `CarbonImmutable` (timestamp *and* timezone), `Entity` (via `equals()`), `BackedEnum` (via `->value`) and `Stringable` (string cast), in that order.

`hydrate(Model $model)` pulls `created_at`, `updated_at` and `version` back off the row. The base mapper does **not** call it — it has no `toEntity()` at all, so calling `hydrate()` is the consuming mapper's job by convention. `Repository::syncEntityFromModel()` calls it again after save.

## Domain Events

`DomainEvent` is an `abstract readonly class` with a **`final` constructor**: `(Id $aggregateId, Collection $eventData = new Collection, int $eventVersion = 1)`. Concrete events therefore add no constructor parameters and override only `eventName()` — the single interface member the base leaves unimplemented:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Blog\Events;

final readonly class ArticleRenamed extends DomainEvent
{
    public function eventName(): string
    {
        return 'domain.article.renamed';
    }
}
```

When you pass `$eventClass` to `updateAggregateRoot()`, it is instantiated as `new $eventClass($this->id(), $changesCollection)` — the payload is the dirty collection with its `old_`/`new_` keys. Need a different payload? Build the event yourself and pass it as the third argument.

Events are buffered on the aggregate (`recordEvent()`, `uncommittedEvents()`, `hasUncommittedEvents()`, `markEventsAsCommitted()`) and dispatched by the repository after a successful save. `uncommittedEvents()` returns clones; `__clone`, `__sleep` and `__wakeup` deliberately drop the buffer.

`recordEvent()` alone is not enough to get an event published — see the dispatch gate in `.claude/infrastructure.md`.

## Contracts

`Domain/Contracts` holds the interfaces the infrastructure implements:

- `Repository` — `nextId(): Uuid`, `exists()`, `delete()`, `all()`, `count()`. `save()`, `find()` and `findOrFail()` are **not** in the base contract; declare them on the per-aggregate interface.
- `AggregateRoot` — the event buffer surface.
- `DomainEvent` — `aggregateId()`, `eventName()`, `occurredOn()`, `eventData()`, `eventVersion()`, `toArray()`.

## Structure

```
Domain/
├── Contracts/      # Repository, AggregateRoot, DomainEvent interfaces
├── Entities/       # Entity, Aggregate
├── Enums/
├── Events/         # DomainEvent base
├── Exceptions/     # ValidationException, EntityNotFound
└── ValueObjects/   # by category, plus ValueObject base and Identity/
```
