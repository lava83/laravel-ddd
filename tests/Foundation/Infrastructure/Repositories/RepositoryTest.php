<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantSaveModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ConcurrencyException;
use Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions\EntityClassNotAvailable;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\AggregateTestMapper;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\CounterTestMapper;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories\AggregateTestRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories\CounterTestRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories\SyncingAggregateTestRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories\UnboundAggregateTestRepository;

/**
 * Repository is DB-backed, but the package harness ships no schema, so each test
 * creates one on the in-memory `testing` connection (fresh per test) and
 * registers the mappers the resolver needs.
 */
beforeEach(function (): void {
    Schema::dropIfExists('aggregate_test_subjects');
    Schema::dropIfExists('counter_test_subjects');

    Schema::create('aggregate_test_subjects', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->unsignedInteger('version')->default(1);
        $table->timestamps();
    });

    Schema::create('counter_test_subjects', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('version')->default(1);
        $table->timestamps();
    });

    entity_mapper_resolver()->registerMapper(AggregateTestSubject::class, new AggregateTestMapper);
    entity_mapper_resolver()->registerMapper(CounterTestSubject::class, new CounterTestMapper);
});

function aggregateRepository(): AggregateTestRepository
{
    return new AggregateTestRepository;
}

function counterRepository(): CounterTestRepository
{
    return new CounterTestRepository;
}

/**
 * Insert a row straight through the model, bypassing the aggregate, so the
 * arrange step does not depend on the code under test and can pin an exact
 * persisted version.
 */
function seedAggregateRow(string $name = 'Original', int $version = 1): EntityTestId
{
    $id = EntityTestId::generate();

    AggregateTestModel::query()->create([
        'id' => $id->value(),
        'name' => $name,
        'version' => $version,
    ]);

    return $id;
}

describe('entityMapper()', function (): void {
    it('throws when the repository has no entity class configured', function (): void {
        expect(fn () => (new UnboundAggregateTestRepository)->mapper())
            ->toThrow(EntityClassNotAvailable::class);
    });

    it('resolves the mapper registered for the entity class', function (): void {
        expect(aggregateRepository()->mapper())->toBeInstanceOf(AggregateTestMapper::class);
    });
});

describe('saveEntity()', function (): void {
    it('inserts a brand-new aggregate as a row', function (): void {
        $aggregate = AggregateTestSubject::create('Fresh');

        aggregateRepository()->save($aggregate);

        $row = AggregateTestModel::query()->find($aggregate->id()->value());

        expect($row)->not->toBeNull()
            ->and($row->name)->toBe('Fresh')
            ->and($row->version)->toBe(1);
    });

    it('rehydrates version and timestamps back onto the aggregate after insert', function (): void {
        $aggregate = AggregateTestSubject::create('Fresh');

        aggregateRepository()->save($aggregate);

        expect($aggregate->version())->toBe(1)
            ->and($aggregate->persistedVersion())->toBe(1)
            ->and($aggregate->createdAt())->toBeInstanceOf(CarbonImmutable::class);
    });

    it('updates an existing row and bumps the stored version', function (): void {
        $id = seedAggregateRow('Original');

        $aggregate = aggregateRepository()->find($id);
        $aggregate->rename('Renamed');

        aggregateRepository()->save($aggregate);

        $row = AggregateTestModel::query()->find($id->value());

        expect($row->name)->toBe('Renamed')
            ->and($row->version)->toBe(2)
            ->and($aggregate->version())->toBe(2)
            ->and($aggregate->persistedVersion())->toBe(2);
    });

    it('does not write when a clean, already-persisted aggregate is saved', function (): void {
        $id = seedAggregateRow('Original');

        $aggregate = aggregateRepository()->find($id);

        expect($aggregate->isDirty())->toBeFalse();

        aggregateRepository()->save($aggregate);

        // The persist gate skipped the write: the stored version is untouched.
        expect(AggregateTestModel::query()->find($id->value())->version)->toBe(1);
    });

    it('collapses several in-memory mutations into a single stored revision', function (): void {
        $id = seedAggregateRow('Original');

        $aggregate = aggregateRepository()->find($id);
        $aggregate->rename('First');
        $aggregate->rename('Second');

        // Two touches -> in-memory version 3, but the guard keys off the loaded
        // version and stores exactly one revision forward: no false conflict.
        aggregateRepository()->save($aggregate);

        $row = AggregateTestModel::query()->find($id->value());

        expect($row->name)->toBe('Second')
            ->and($row->version)->toBe(2)
            ->and($aggregate->version())->toBe(2);
    });

    it('throws CantSaveModel when the underlying insert fails', function (): void {
        AggregateTestModel::saving(static fn () => false);

        expect(fn () => aggregateRepository()->save(AggregateTestSubject::create('Doomed')))
            ->toThrow(CantSaveModel::class);
    });
});

describe('optimistic locking (updateWithVersionGuard)', function (): void {
    it('throws ConcurrencyException when the row advanced under a stale aggregate', function (): void {
        $id = seedAggregateRow('Original');

        $first = aggregateRepository()->find($id);
        $second = aggregateRepository()->find($id);

        $first->rename('First writer');
        aggregateRepository()->save($first);

        $second->rename('Second writer');

        expect(fn () => aggregateRepository()->save($second))
            ->toThrow(ConcurrencyException::class);
    });

    it('leaves the row on the first write after a rejected save', function (): void {
        $id = seedAggregateRow('Original');

        $first = aggregateRepository()->find($id);
        $second = aggregateRepository()->find($id);

        $first->rename('First writer');
        aggregateRepository()->save($first);

        $second->rename('Second writer');

        try {
            aggregateRepository()->save($second);
        } catch (ConcurrencyException) {
            // expected — asserted elsewhere; here we check the row is intact
        }

        $row = AggregateTestModel::query()->find($id->value());

        expect($row->name)->toBe('First writer')
            ->and($row->version)->toBe(2);
    });
});

describe('domain events', function (): void {
    it('dispatches events recorded on a new aggregate after insert', function (): void {
        Event::fake();

        aggregateRepository()->save(AggregateTestSubject::create('Fresh'));

        Event::assertDispatched(AggregateTestCreated::class);
    });

    it('dispatches events recorded during an update', function (): void {
        $id = seedAggregateRow('Original');
        $aggregate = aggregateRepository()->find($id);
        $aggregate->rename('Renamed');

        Event::fake();
        aggregateRepository()->save($aggregate);

        Event::assertDispatched(AggregateTestRenamed::class);
    });

    it('dispatches nothing when the persist gate skips a clean aggregate', function (): void {
        $id = seedAggregateRow('Original');
        $aggregate = aggregateRepository()->find($id);

        Event::fake();
        aggregateRepository()->save($aggregate);

        Event::assertNotDispatched(AggregateTestRenamed::class);
        Event::assertNotDispatched(AggregateTestCreated::class);
    });
});

describe('syncDependencies() hook', function (): void {
    it('runs the hook during saveEntity, before domain events dispatch', function (): void {
        $repository = new SyncingAggregateTestRepository;

        // Real dispatch (no Event::fake) so the listener actually fires and
        // we can observe ordering relative to the hook.
        Event::listen(
            AggregateTestCreated::class,
            function () use ($repository): void {
                $repository->log[] = 'event';
            },
        );

        $repository->save(AggregateTestSubject::create('Fresh'));

        // Option A's seam: dependencies are synced first, only then are events published.
        expect($repository->log)->toBe(['synced', 'event']);
    });

    it('hands the hook the already-persisted model', function (): void {
        $repository = new SyncingAggregateTestRepository;
        $aggregate = AggregateTestSubject::create('Fresh');

        $repository->save($aggregate);

        expect($repository->syncedModel)->not->toBeNull()
            ->and($repository->syncedModel->exists)->toBeTrue()
            ->and($repository->syncedModel->getKey())->toBe($aggregate->id()->value());
    });
});

describe('deleteEntity()', function (): void {
    it('removes the row', function (): void {
        $id = seedAggregateRow('Original');
        $aggregate = aggregateRepository()->find($id);

        aggregateRepository()->remove($aggregate);

        expect(AggregateTestModel::query()->find($id->value()))->toBeNull();
    });

    it('dispatches the aggregate uncommitted events', function (): void {
        $id = seedAggregateRow('Original');
        $aggregate = aggregateRepository()->find($id);
        $aggregate->rename('About to go');

        Event::fake();
        aggregateRepository()->remove($aggregate);

        Event::assertDispatched(AggregateTestRenamed::class);
    });

    it('throws CantDeleteModel when the delete fails', function (): void {
        $id = seedAggregateRow('Original');
        $aggregate = aggregateRepository()->find($id);

        AggregateTestModel::deleting(static fn () => false);

        expect(fn () => aggregateRepository()->remove($aggregate))
            ->toThrow(CantDeleteModel::class);
    });
});

describe('deleteEntities()', function (): void {
    it('deletes every aggregate in the collection', function (): void {
        $first = seedAggregateRow('First');
        $second = seedAggregateRow('Second');

        aggregateRepository()->removeMany(collect([
            aggregateRepository()->find($first),
            aggregateRepository()->find($second),
        ]));

        expect(AggregateTestModel::query()->count())->toBe(0);
    });
});

describe('auto-increment identity (integer keys)', function (): void {
    it('lets the database assign the key and flows it back onto the aggregate', function (): void {
        $counter = CounterTestSubject::create('one');

        counterRepository()->save($counter);

        expect($counter->id()->value())->toBe(1)
            ->and(CounterTestModel::query()->find(1))->not->toBeNull();
    });

    it('does not clobber the sequence on a second insert', function (): void {
        $first = CounterTestSubject::create('one');
        $second = CounterTestSubject::create('two');

        counterRepository()->save($first);
        counterRepository()->save($second);

        expect($first->id()->value())->toBe(1)
            ->and($second->id()->value())->toBe(2);
    });
});
