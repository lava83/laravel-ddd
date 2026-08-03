<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\BoundModelClassTestMapper;
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
