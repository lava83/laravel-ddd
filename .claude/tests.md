# Tests — `tests/**`

Pest 4 with Orchestra Testbench 11. Suites live under `tests/Foundation`, mirroring `src/`.

## Harness

`tests/TestCase.php` extends Testbench, registers `LaravelDddServiceProvider`, points factory discovery at `Lava83\LaravelDdd\Database\Factories\`, and sets `database.default = testing`.

`tests/Pest.php` applies to `Foundation` and, in `beforeEach`:

- `Str::createRandomStringsNormally()` and `Str::createUuidsNormally()`
- `Http::preventStrayRequests()`
- `Sleep::fake()`
- `$this->freezeTime()`

So: no test may depend on real wall-clock time or make an outbound request. Time is frozen — if you need it to advance, use `travel()`.

## Runner strictness

`phpunit.xml.dist` sets `executionOrder="random"`, `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite` and `beStrictAboutOutputDuringTests`. Consequences:

- Order-dependent tests fail intermittently. No shared mutable state between tests.
- A leftover `dump()`, `dd()` or `echo` fails the run.
- An empty or fully skipped file fails the run.

JUnit output goes to `build/report.junit.xml`.

## Style

```php
<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

describe('Uuid', function (): void {
    it('generates a v7 uuid', function (): void {
        expect(Uuid::generate()->value())->toBeString();
    });

    it('rejects a malformed string', function (): void {
        expect(fn () => Uuid::fromString('nope'))
            ->toThrow(ValidationException::class);
    });
});
```

- `describe()` groups by class or behaviour, `it()` reads like a sentence, `expect()` for assertions
- `beforeEach()` for per-test setup inside a `describe()` block
- One file per class under test, path mirroring the source path

`tests/Foundation/Domain/ValueObjects/Identity/UuidTest.php` is currently the only file without a `describe()` wrapper — the convention, not the exception, is what to follow.

## What to cover for each building block

- **Value Object** — construction from valid input, rejection of invalid input, `equals()`, string/JSON serialisation, immutability (the original is unchanged after a `with*()` call)
- **Entity** — invariant violations throw from the constructor, `updateEntity()` bumps version and `updatedAt`, unchanged input produces an empty dirty collection, non-promoted properties are *not* applied
- **Aggregate** — an event is recorded on state change, `uncommittedEvents()` returns clones, `markEventsAsCommitted()` empties the buffer, clone/serialise drops it
- **Mapper** — round trip entity → model → entity preserves identity, version and timestamps
- **Repository** — needs a database (see below)

## Running

```bash
composer test
vendor/bin/pest tests/Foundation/Domain/ValueObjects/Identity/UuidTest.php
vendor/bin/pest --filter="Uuid"
composer test-coverage   # writes storage/coverage, untracked and not gitignored
```

## Two structural gaps

**No database.** There is no `database/` directory, and the migration loader in `TestCase::getEnvironmentSetUp()` is commented out. Repository, mapper-round-trip and optimistic-locking tests are not possible until that scaffolding exists — adding it is an architecture decision, so propose it rather than building it.

**No arch tests.** `pestphp/pest-plugin-arch` is installed with zero `arch()` tests, while the structural rules Mago used to enforce are unenforced. That plugin is the obvious home for them.

Coverage today: 20 files, 17 of them Infrastructure filters. `Entity`, `Aggregate`, `Repository`, `EntityMapper`, `EntityMapperResolver`, optimistic locking and event dispatch have no tests at all. A green suite is not evidence that these work.
