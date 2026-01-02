---
applyTo: "tests/**"
---

# Test Instructions (Pest PHP)

All tests in this project use **Pest PHP** - a testing framework with an expressive API.

## Test Structure

```php
<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Email;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;

describe('Email Value Object', function (): void {

    it('creates from valid email string', function (): void {
        $email = Email::fromString('test@example.com');

        expect($email->toString())->toBe('test@example.com');
    });

    it('normalizes email to lowercase', function (): void {
        $email = Email::fromString('Test@EXAMPLE.com');

        expect($email->toString())->toBe('test@example.com');
    });

    it('throws ValidationException for invalid email', function (): void {
        expect(fn() => Email::fromString('invalid'))
            ->toThrow(ValidationException::class);
    });

    it('compares two emails for equality', function (): void {
        $email1 = Email::fromString('test@example.com');
        $email2 = Email::fromString('test@example.com');
        $email3 = Email::fromString('other@example.com');

        expect($email1->equals($email2))->toBeTrue();
        expect($email1->equals($email3))->toBeFalse();
    });

});
```

## Pest Conventions

### Use `describe()` for Grouping

```php
describe('Class or Feature Name', function (): void {
    // Related tests
});
```

### Use `it()` for Test Cases

```php
it('does something specific', function (): void {
    // Arrange, Act, Assert
});
```

### Use `expect()` for Assertions

```php
// Basic assertions
expect($value)->toBe('exact');
expect($value)->toEqual('loose');
expect($value)->toBeTrue();
expect($value)->toBeFalse();
expect($value)->toBeNull();
expect($value)->toBeInstanceOf(ClassName::class);

// Collection/Array assertions
expect($collection)->toHaveCount(3);
expect($array)->toContain('item');
expect($array)->toHaveKey('key');

// Exception assertions
expect(fn() => $action())->toThrow(ExceptionClass::class);
expect(fn() => $action())->toThrow(ExceptionClass::class, 'message');

// String assertions
expect($string)->toContain('substring');
expect($string)->toStartWith('prefix');
expect($string)->toMatch('/pattern/');
```

### Use `beforeEach()` for Setup

```php
describe('Repository', function (): void {
    
    beforeEach(function (): void {
        $this->repository = new InMemoryRepository();
    });

    it('stores entity', function (): void {
        // Use $this->repository
    });

});
```

## Test Naming

Use descriptive names that read like sentences:

```php
// ✅ Good
it('creates email from valid string')
it('throws exception when email is empty')
it('extracts domain from email address')

// ❌ Bad
it('test1')
it('email works')
it('testCreateEmail')
```

## Testing Value Objects

```php
describe('Money Value Object', function (): void {

    it('creates from cents', function (): void {
        $money = Money::euros(1500); // 15.00 EUR
        
        expect($money->amount())->toBe(15.0);
        expect($money->currency())->toBe('EUR');
    });

    it('is immutable', function (): void {
        $money1 = Money::euros(1000);
        $money2 = $money1->add(Money::euros(500));
        
        // Original unchanged
        expect($money1->amount())->toBe(10.0);
        expect($money2->amount())->toBe(15.0);
    });

    it('prevents negative amounts', function (): void {
        expect(fn() => new Money(-100, 'EUR'))
            ->toThrow(InvalidArgumentException::class);
    });

});
```

## Testing Entities

```php
describe('Board Aggregate', function (): void {

    it('creates with factory method', function (): void {
        $board = Board::create(
            id: BoardId::generate(),
            name: 'Test Board',
        );

        expect($board->name())->toBe('Test Board');
        expect($board->hasUncommittedEvents())->toBeTrue();
    });

    it('records domain event on creation', function (): void {
        $board = Board::create(/* ... */);
        
        $events = $board->uncommittedEvents();
        
        expect($events)->toHaveCount(1);
        expect($events->first())->toBeInstanceOf(BoardCreated::class);
    });

    it('increments version on update', function (): void {
        $board = Board::create(/* ... */);
        $initialVersion = $board->version();
        
        $board->rename('New Name');
        
        expect($board->version())->toBe($initialVersion + 1);
    });

});
```

## Testing Repositories (Integration)

```php
describe('Eloquent Board Repository', function (): void {

    beforeEach(function (): void {
        // Run migrations or use RefreshDatabase trait
    });

    it('persists and retrieves board', function (): void {
        $repository = app(BoardRepositoryInterface::class);
        
        $board = Board::create(/* ... */);
        $repository->save($board);
        
        $retrieved = $repository->find($board->id());
        
        expect($retrieved)->not->toBeNull();
        expect($retrieved->id()->equals($board->id()))->toBeTrue();
    });

});
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific file
./vendor/bin/pest tests/Domain/ValueObjects/EmailTest.php

# Run with filter
./vendor/bin/pest --filter="Email"

# Run with coverage
composer test-coverage
```
