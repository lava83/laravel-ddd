# Laravel DDD Foundation Package - Copilot Instructions

## Project Overview

This is a DDD (Domain-Driven Design) foundation package for Laravel 13 applications. It provides base classes for Aggregates, Entities, Value Objects, Repositories, and Domain Events.

## Tech Stack

- **PHP**: 8.4+
- **Laravel**: 13 (`illuminate/contracts` `^13.0`)
- **Testing**: Pest PHP
- **Static Analysis & Linting**: PHPStan / Larastan (with baseline)
- **Code Style**: Laravel Pint
- **Layering**: Deptrac (Domain → Application → Facades → Infrastructure)
- **Key Dependencies**: Spatie Laravel Data, Eloquent Filtering (`indexzer0/eloquent-filtering`), libphonenumber-for-php, Laravel Sqid

## Architecture - Layer Separation

This package follows strict DDD layer separation:

```
src/
├── Domain/           # Pure PHP, business logic, no framework dependencies
├── Application/      # Orchestration, use cases, minimal Laravel
└── Infrastructure/   # Laravel/Eloquent implementations
```

### Layer Rules

1. **Domain Layer** (`src/Domain/`):
    - Pure PHP only
    - Laravel helpers allowed: `collect()`, `str()`, `fluent()`, `now()`
    - NO Eloquent, NO database concerns (except on entities and aggregates: `fromState()` - method. This is a deprecated feature and should be avoided in the future.)
    - All classes must be immutable

2. **Application Layer** (`src/Application/`):
    - Orchestration logic only
    - No business rules (those belong in Domain)
    - Use repositories, not direct database access

3. **Infrastructure Layer** (`src/Infrastructure/`):
    - Laravel/Eloquent implementations
    - Mappers translate between Entity ↔ Model
    - Repositories handle persistence and events

## CRITICAL: Immutability Rules

**Everything in the Domain layer MUST be immutable.**

### Value Objects

```php
// ✅ CORRECT - Immutable Value Object
class Email extends ValueObject
{
    private readonly Stringable $value;
    
    public function withDomain(string $domain): self
    {
        return new self($this->localPart . '@' . $domain);
    }
}

// ❌ WRONG - Mutable (NEVER do this)
class Email extends ValueObject
{
    private string $value; // Missing readonly!
    
    public function setDomain(string $domain): void
    {
        $this->value = $this->localPart . '@' . $domain; // Mutation!
    }
}
```

### Entities

```php
// ✅ CORRECT - Entity with immutable identity
class Member extends Entity
{
    public function __construct(
        private readonly MemberId $id,  // Identity is readonly
        protected string $name,          // State can change via methods
    ) {}
    
    public function rename(string $name): void
    {
        $this->updateEntity(['name' => $name]);
    }
}
```

### Domain Events

```php
// ✅ CORRECT - Readonly Domain Event
readonly class BoardCreated extends DomainEvent
{
    public function eventName(): string
    {
        return 'board.created';
    }
}
```

### Rules Summary

- Use `readonly` on ALL Value Object properties
- Use `readonly` on Entity identity properties
- Use `CarbonImmutable`, NOT `Carbon`
- Methods that "change" state return new instances
- NO setter methods in Value Objects
- Domain Events must be `readonly class`

## Coding Standards

### Naming Conventions

- Classes: `PascalCase`
- Methods/Properties: `camelCase`
- Constants: `SCREAMING_SNAKE_CASE`
- Files: Match class name exactly

### Type Safety

- Always use strict types: `declare(strict_types=1);`
- Always type properties, parameters, and return types
- Use union types sparingly, prefer interfaces
- Use `Collection<TKey, TValue>` generic annotations

### PHPDoc

```php
/**
 * Short description.
 *
 * @param Collection<int, Entity> $entities
 * @return array<string, mixed>
 * @throws ValidationException
 */
```

## Testing with Pest

- All tests use Pest PHP framework
- Use `describe()` blocks for grouping
- Use `it()` for individual test cases
- Use `expect()` for assertions

```php
describe('Email Value Object', function (): void {
    it('creates from valid email string', function (): void {
        $email = Email::fromString('test@example.com');
        
        expect($email->toString())->toBe('test@example.com');
    });
    
    it('throws on invalid email', function (): void {
        expect(fn() => Email::fromString('invalid'))
            ->toThrow(ValidationException::class);
    });
});
```

## Build & Test Commands

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Format code
composer format

# Static analysis (PHPStan / Larastan)
composer analyse

# Check architectural layering (Deptrac)
composer deptrac

# Everything at once: format + analyse + deptrac + tests
composer qa
```

## Common Patterns

### Factory Methods

Always use static factory methods for Value Objects:

```php
public static function fromString(string $value): static
public static function fromArray(array $data): static
public static function generate(): static  // For IDs
```

### Repository Pattern

```php
public function find(Uuid $id): ?Entity;
public function findOrFail(Uuid $id): Entity;
public function save(Entity $entity): void;
public function delete(Entity $entity): void;
```

### Entity-Model Mapping

```php
class MemberMapper implements EntityMapper
{
    public static function toEntity(Model $model, bool $deep = false): Entity;
    public static function toModel(Entity $entity): Model;
}
```
