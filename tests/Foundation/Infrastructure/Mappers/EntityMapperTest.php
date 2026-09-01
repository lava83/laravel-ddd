<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\BoundModelClassTestMapper;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\CounterTestMapper;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\UnboundModelClassTestMapper;

describe('EntityMapper::$modelClass', function (): void {
    describe('left undefined (the base default)', function (): void {
        it('defaults to null', function (): void {
            expect(UnboundModelClassTestMapper::readModelClass())->toBeNull();
        });

        it('makes the guard throw a RuntimeException', function (): void {
            expect(fn () => UnboundModelClassTestMapper::runGuard())
                ->toThrow(RuntimeException::class, 'Model class is not defined.');
        });

        it('makes findOrCreateModelFillData throw before it queries', function (): void {
            expect(fn () => UnboundModelClassTestMapper::toModel(EntityTestSubject::create('Stack')))
                ->toThrow(RuntimeException::class, 'Model class is not defined.');
        });
    });

    describe('bound by a subclass', function (): void {
        it('is resolved through late static binding, not off the base class', function (): void {
            expect(BoundModelClassTestMapper::readModelClass())->toBe(EntityTestModel::class);
        });

        it('satisfies the guard without throwing', function (): void {
            expect(fn () => BoundModelClassTestMapper::runGuard())
                ->not->toThrow(RuntimeException::class);
        });
    });
});

/**
 * The fill path (findOrCreateModelFillData) queries the database, so each test
 * builds an ephemeral schema on the in-memory `testing` connection — the same
 * pattern RepositoryTest uses. These exercise toModel() directly, without a
 * repository, which is exactly the Entity::toState()->save() case the key
 * handling exists for.
 */
describe('EntityMapper::toModel()', function (): void {
    beforeEach(function (): void {
        Schema::dropIfExists('entity_test_subjects');
        Schema::dropIfExists('counter_test_subjects');

        Schema::create('entity_test_subjects', function (Blueprint $table): void {
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
    });

    describe('auto-incrementing integer key', function (): void {
        it('nulls the Integer::new() placeholder on a fresh entity so the database owns the key', function (): void {
            $model = CounterTestMapper::toModel(CounterTestSubject::create('one'));

            expect($model->exists)->toBeFalse()
                ->and($model->getKey())->toBeNull();
        });

        it('lets the database sequence assign keys on save, not a max()+1 read', function (): void {
            $first = CounterTestMapper::toModel(CounterTestSubject::create('one'));
            $first->save();

            $second = CounterTestMapper::toModel(CounterTestSubject::create('two'));
            $second->save();

            expect($first->getKey())->toBe(1)
                ->and($second->getKey())->toBe(2);
        });

        it('leaves the key of an already-persisted row untouched', function (): void {
            CounterTestModel::query()->create(['id' => 5, 'name' => 'seed']);

            $model = CounterTestMapper::toModel(
                new CounterTestSubject(IntegerTestId::fromInt(5), 'seed'),
            );

            expect($model->exists)->toBeTrue()
                ->and($model->getKey())->toBe(5);
        });
    });

    describe('non-incrementing string key', function (): void {
        it('keeps the entity id and never nulls a non-incrementing key', function (): void {
            $entity = EntityTestSubject::create('Stack');

            $model = BoundModelClassTestMapper::toModel($entity);

            expect($model->exists)->toBeFalse()
                ->and($model->getKey())->toBe($entity->id()->value());
        });
    });

    describe('default fields (mergeWithDefaultData)', function (): void {
        it('round-trips version and timestamps through fill and reconstitution', function (): void {
            $entity = CounterTestSubject::create('one');

            $model = CounterTestMapper::toModel($entity);
            $model->save();

            $reconstituted = CounterTestMapper::toEntity(
                CounterTestModel::query()->findOrFail($model->getKey()),
            );

            expect($reconstituted->version())->toBe(1)
                ->and($reconstituted->createdAt())->toBeInstanceOf(CarbonImmutable::class)
                ->and($reconstituted->createdAt()->format('Y-m-d H:i:s'))
                ->toBe($entity->createdAt()->format('Y-m-d H:i:s'));
        });
    });
});
