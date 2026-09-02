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
- **Repository** — DB-backed; build the schema per test (see below). Cover insert/update/version bump, the persist gate skipping a clean aggregate, optimistic-locking rejection, the `syncDependencies()` hook running before dispatch, and after-commit event dispatch

## Running

```bash
composer test
vendor/bin/pest tests/Foundation/Domain/ValueObjects/Identity/UuidTest.php
vendor/bin/pest --filter="Uuid"
composer test-coverage   # writes storage/coverage, untracked and not gitignored
```

## Database-backed tests

There is still no `database/` directory and no migration loader, but DB-backed suites no longer need one: they build their schema per test on the in-memory `testing` connection. `RepositoryTest` is the reference — its `beforeEach()` does `Schema::dropIfExists()` + `Schema::create()` for the fixture tables and registers the mappers, so each test starts from a known schema with no shared state.

`Entity`, `Aggregate`, `EntityMapper`, `Repository` and `DomainEventPublisher` now have their own suites; the old "the advertised core is untested" caveat no longer holds. Still reason from the code, but check the relevant suite rather than assuming a building block is unexercised.

### Testing the save-path seams

- **`syncDependencies()` ordering.** Subclass the base `Repository` in a fixture that overrides the hook and records when it fires, register a real listener that records when the event fires, and assert the hook ran first. See `SyncingAggregateTestRepository` and `RepositoryTest`'s `syncDependencies() hook` group.
- **After-commit dispatch.** Use a *real* dispatcher — **not** `Event::fake()`, which swaps the dispatcher and bypasses `DB::afterCommit()` — and drive it inside a `DB::transaction()`: assert the event has not dispatched *inside* the transaction and *has* after it commits, and assert a thrown exception (rollback) drops it. See `DomainEventPublisherTest`'s `transaction-aware dispatch` group.

## No arch tests

`pestphp/pest-plugin-arch` is installed with zero `arch()` tests, while the structural rules Mago used to enforce are unenforced. That plugin is the obvious home for them.
