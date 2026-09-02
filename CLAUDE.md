# CLAUDE.md

Guidance for Claude Code working in `lava83/laravel-ddd`.

## Your role: sparring partner, not implementer

You do **not** write production code in this repo. You support through:

- Architecture and API-design discussion (layering, extensibility of the base building blocks)
- Code review and pattern assessment — DDD correctness first, code quality second
- Debugging and error analysis
- Conceptual clarification (Entity, Aggregate, Value Object, Repository, Mapper, Domain Event, Optimistic Locking)

**You may write:** stubs, class skeletons, trivial base-class extensions, test scaffolding, config snippets, docs.

**You may not write:** complete feature implementations, unsolicited refactorings, changes across multiple layers at once.

Rules of engagement:

- Ask before assuming. Never invent APIs, config keys, or method names — read the file or say you don't know.
- Never claim to have "seen" code that was not shown to you or read by you.
- No design lectures, no padding. Answer, then stop.
- Larger change? Propose a plan and wait for approval.

## What this package is

DDD foundation for Laravel — the building blocks that consuming applications (e.g. `waz-api`) build on.

Core blocks: `Entity` / `Aggregate` (versioning, timestamps, change application), `ValueObject` + built-in VOs, `Repository` base with entity↔model mapping and transactions, `EntityMapper` / `EntityMapperResolver`, `DomainEvent` with automatic dispatch on save, optimistic locking via `ConcurrencyException`, `Model` base with `Filterable`, entity binding and fillable merging (`id`, `version`, timestamps). UUID keys are **opt-in** via `Infrastructure\Models\Concerns\HasUuids` (UUIDv7), not on the base `Model`.

Stack: PHP 8.4+, Laravel 13 (`illuminate/contracts` ^13.0), Pest 4, Orchestra Testbench 11, `spatie/laravel-data`, `spatie/laravel-package-tools`, `indexzer0/eloquent-filtering`, `libphonenumber-for-php`, `lava83/laravel-sqid`.

## Commands

```bash
composer qa            # lint + test — the full gate
composer lint          # format + analyse + deptrac
composer format        # pint (WRITES files)
composer format-check  # pint --test (read-only)
composer analyse       # phpstan/larastan, level 6
composer deptrac       # architecture layering
composer test          # pest
composer test-coverage # pest --coverage --coverage-html=storage/coverage
```

`composer prepare` (`testbench package:discover`) runs automatically on `post-autoload-dump`.

`composer lint` runs `format`, not `format-check` — it mutates the working tree. Use `format-check` when you only want to verify.

`composer test-coverage` writes to `storage/coverage`, which is neither present nor gitignored — it leaves untracked files behind.

Run `composer qa` before proposing anything as done. If a gate fails, report the failure — do not silence it.

## Layering: Deptrac is the law

`deptrac.yaml` defines seven layers; the ruleset has six entries. Allowed dependencies:

| Layer | May depend on |
| --- | --- |
| `Domain` | `AllowedPrimitives`, `InfraModel` |
| `Application` | `Domain`, `AllowedPrimitives` |
| `Facades` | `Root`, `Domain`, `AllowedPrimitives` |
| `Infrastructure` | `Domain`, `Application`, `InfraModel`, `AllowedPrimitives` |
| `InfraModel` | `Domain`, `Infrastructure`, `AllowedPrimitives` |
| `Root` | all five others + `AllowedPrimitives` |

Two deliberate exceptions:

- **`InfraModel`** is `Infrastructure\Models\Model` hoisted into its own layer, so the Domain may touch it (legacy `Entity::fromState()`). Deprecated — do not add new usages.
- **`AllowedPrimitives`** lists the vendor classes the Domain is meant to use: `Illuminate\Support\Collection`, `Illuminate\Support\Stringable`, `Illuminate\Validation\Rule`, `Illuminate\Database\RecordsNotFoundException`, `Carbon\CarbonImmutable`, `Carbon\CarbonInterface`, `Ramsey\Uuid\*`, `libphonenumber\*`.

**`AllowedPrimitives` documents intent; it does not enforce it.** Deptrac ignores dependencies on classes belonging to no layer, so the Domain can import any other vendor class and any global helper (`collect()`, `now()`, `validator()`, `entity_mapper_resolver()`) with a green run. Purity in the Domain is a review job, not a CI job. Extending the list is an architecture decision — propose it, don't do it.

## Non-negotiables

- `declare(strict_types=1);` in every PHP file (Pint enforces it)
- Everything in `Domain` is immutable: `readonly` on all VO properties, `readonly` on entity identity, `readonly class` for Domain Events, no setters — state changes return new instances
- `CarbonImmutable`, never `Carbon`
- Type every property, parameter and return value
- `Collection<TKey, TValue>` generics with the **key type spelled out** — PHPStan and PHPStorm only narrow correctly with both (e.g. `Collection<class-string<Entity>, EntityMapper>`)
- Static factory methods on VOs: `fromString()`, `fromArray()`, `generate()`
- Eloquent models extend `Lava83\LaravelDdd\Infrastructure\Models\Model`

## Naming and documentation

- Classes `PascalCase`, methods and properties `camelCase`, constants `SCREAMING_SNAKE_CASE`, filename matches the class name exactly
- Prefer interfaces over union types
- PHPDoc carries what the signature cannot: generics, array shapes, `@throws`, `@template`, `@extends`, `@implements`, `@mixin`. Don't restate the signature in prose.

## Layer conventions

Templates, real base-class signatures and per-layer gotchas live next to this file. Read the one for the layer you're touching **before** reviewing or scaffolding:

- `.claude/domain.md` — Value Objects, identities, `Entity` / `Aggregate` change tracking, Domain Events
- `.claude/application.md` — Application Services, Controllers, Resources
- `.claude/infrastructure.md` — Models, Mappers, mapper registration, Repositories, events, optimistic locking, exceptions
- `.claude/tests.md` — Pest harness, runner strictness, what to cover per building block

They describe the code as it actually is, verified against `src/`. If your reading of the source contradicts them, the source wins — say so instead of quietly diverging.

## Static analysis

`phpstan.neon`: level 6, `src` only, `reportUnmatchedIgnoredErrors: true`, **no baseline**.

The single ignore is `trait.unused` for `src/**/Concerns/*`.

- Do not add a baseline. Do not add `ignoreErrors` entries. Fix the finding or explain why it's a false positive and ask.
- `@phpstan-ignore` inline is acceptable only for legitimate runtime-narrowing defenses, with a comment saying why.
- Escalation to level 8/9 is planned but not decided — don't preempt it.
- Assess before suppressing: run the tool without suppression first to see the full damage, then decide what to fix vs. ignore.

## Tests

Pest 4 in `tests/`, suites under `tests/Foundation`, package harness via Orchestra Testbench (`TestCase` registers `LaravelDddServiceProvider`).

`tests/Pest.php` freezes time and fakes `Sleep`, resets `Str` randomness, and calls `Http::preventStrayRequests()`. Tests must not depend on real wall-clock time or outbound HTTP.

Style: `describe()` for grouping, `it('reads like a sentence')`, `expect()` for assertions. PHPUnit runs with `executionOrder="random"`, `failOnWarning`, `failOnRisky`, `failOnEmptyTestSuite` and `beStrictAboutOutputDuringTests` — order-dependent tests break, and a stray `dump()` or `echo` fails the run.

```bash
vendor/bin/pest tests/Foundation/Domain/ValueObjects/Identity/UuidTest.php
vendor/bin/pest --filter="Uuid"
```

There is no `database/` directory, and the migration loader in `TestCase::getEnvironmentSetUp()` is commented out — DB-backed integration tests are not currently possible without adding that scaffolding first.

## Commits & PRs

- Conventional Commits: `type(scope): description`. Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`. PR titles are checked by CodeRabbit in warning mode — advisory, not a blocking CI gate.
- Phased, isolated commits: pure style changes separate from logic and type fixes. Never mix a Pint run into a feature commit.
- CI on PHP 8.4 / Laravel 13: `run-qm.yml` (Pint `--test`, PHPStan, Deptrac; push + PR), `run-tests.yml` (Pest; **push only — tests do not run on pull requests**), `update-changelog.yml` (on release).
- CodeRabbit auto-reviews PRs (`chill` profile, request-changes workflow) with per-layer instructions from `.coderabbit.yaml`.
- `CHANGELOG.md` is generated on release — do not edit it by hand.
- `composer.lock` is gitignored, and CI runs `composer update`. Dependency resolution is never reproducible; suspect a fresh upstream release before blaming the diff for a CI-only failure.

## Known gaps — do not treat green as proof

- **A green suite still isn't full proof, but the core is now covered.** `Entity`, `Aggregate`, `EntityMapper`, `Repository` and `DomainEventPublisher` have their own suites (`RepositoryTest` builds its schema per test on the `testing` connection); most of the remaining files are the Infrastructure filters. Read the relevant suite before assuming a building block is or isn't exercised.
- **Optimistic locking works now.** `updateWithVersionGuard()` sets `version = persistedVersion() + 1` and issues `UPDATE ... WHERE version = <base>`, throwing `ConcurrencyException` when no row matches. It's optimistic (a guarded `UPDATE`), not pessimistic (no `lockForUpdate()`), and `RepositoryTest` covers the stale-writer rejection. See `.claude/infrastructure.md`.
- **Domain events now dispatch on every save with uncommitted events, and after the transaction commits.** `saveEntity()` calls `dispatchUncommittedEvents()` unconditionally (the old dirty-gated drop is gone), and `DomainEventPublisher` defers each dispatch via `DB::afterCommit()` — so a rollback drops the events and listeners run post-commit. The seam for persisting dependent rows *before* dispatch is the protected `syncDependencies()` hook. See `.claude/infrastructure.md`.
- `pestphp/pest-plugin-arch` is installed with zero `arch()` tests. That is the tool for the structural rules currently left unenforced.
- Existing purity violations in the Domain that Deptrac cannot see: `Illuminate\Support\Carbon` in `Entity.php` (mutable Carbon, against the non-negotiable above), `Illuminate\Support\Facades\Validator` in `Email.php`, `Illuminate\Support\Fluent` in `Json.php`. Known, not yet decided — do not "fix" them unasked.
- `run-qm.yml` is internally named `run-qa` and its `paths:` filters reference a nonexistent `.github/workflows/run-qa.yml`, so edits to that workflow do not retrigger it.
- Renovate and Dependabot are both active on `composer`, with overlapping scope.

## Don't

- Don't commit or push unless asked.
- Don't touch `vendor/`, `build/`, `.phpunit.cache/`, `.deptrac.cache`.
- Don't add dependencies. Renovate and Dependabot manage versions; new packages are Stefan's call.
- Don't reintroduce Mago. It was fully replaced by Pint + PHPStan + Deptrac. Structural rules Mago once enforced (`final readonly` controllers, enum-only namespaces) are deliberately unenforced for now.
- Don't refactor "while you're in there."
