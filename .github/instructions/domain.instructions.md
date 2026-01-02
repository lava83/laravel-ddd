---
applyTo: "src/Domain/**"
---

# Domain Layer Instructions

This is the **Domain Layer** - the heart of the DDD architecture.

## Absolute Rules

### 1. No Framework Dependencies

- ❌ NO `use Illuminate\Database\*`
- ❌ NO `use Illuminate\Http\*`
- ❌ NO Eloquent Models (except on entities and aggregates: `fromState()` - method. This is a deprecated feature and should be avoided in the future.)
- ✅ Laravel helpers OK: `collect()`, `str()`, `fluent()`, `now()`, `validator()`
- ✅ `Illuminate\Support\Collection` OK
- ✅ `Illuminate\Support\Stringable` OK

### 2. Immutability is Mandatory

**Value Objects:**
```php
// ALL properties MUST be readonly
private readonly string $value;
private readonly Stringable $email;
private readonly CarbonImmutable $date;

// NO setters - return new instances instead
public function withValue(string $value): self
{
    return new self($value);
}
```

**Entities:**
```php
// Identity MUST be readonly
private readonly Uuid $id;

// Use CarbonImmutable for timestamps
protected CarbonImmutable $createdAt;
protected ?CarbonImmutable $updatedAt;
```

**Domain Events:**
```php
// MUST be readonly class
readonly class UserCreated extends DomainEvent
```

### 3. Value Object Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\{Category};

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class {Name} extends ValueObject
{
    private readonly {Type} $value;

    /**
     * @throws ValidationException
     */
    final public function __construct({Type} $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): {Type}
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private function validate({Type} $value): void
    {
        // Validation logic - throw ValidationException on failure
    }
}
```

### 4. Entity Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Entities;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

class {Name} extends Entity
{
    public function __construct(
        private readonly Uuid $id,
        protected string $name,
    ) {
        parent::__construct();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->updateEntity(['name' => $name]);
    }

    public static function fromState(Model $model): self
    {
        // Hydration from persistence - use Mapper instead
    }
}
```

### 5. Domain Event Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Events;

readonly class {Name} extends DomainEvent
{
    public function eventName(): string
    {
        return 'domain.{aggregate}.{action}';
    }
}
```

## Directory Structure

```
Domain/
├── Actions/           # Domain actions (self-contained operations)
├── Contracts/         # Interfaces (Repository, Service contracts)
├── Entities/          # Entities and Aggregates
├── Enums/             # Domain enumerations
├── Events/            # Domain Events
├── Exceptions/        # Domain-specific exceptions
└── ValueObjects/      # Value Objects by category
    ├── Business/      # Money, Tax, etc.
    ├── Communication/ # Email, Phone, Link
    ├── Content/       # Color, Text, etc.
    ├── Data/          # Json, etc.
    ├── Date/          # DateRange, etc.
    └── Identity/      # Uuid, MongoObjectId, etc.
```

## Validation

Always validate in constructor or factory method:

```php
private function validate(string $value): void
{
    if (blank($value)) {
        throw new ValidationException('Value cannot be empty');
    }
    
    // Use Laravel validator for complex rules
    $validator = validator(['field' => $value], [
        'field' => ['required', 'email'],
    ]);
    
    if ($validator->fails()) {
        throw new ValidationException('Invalid value');
    }
}
```
