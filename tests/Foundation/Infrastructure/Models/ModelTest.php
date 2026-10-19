<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Exceptions\EntityClassNotAvailable;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\ModelWithoutEntityTestModel;

describe('a model without an entity class', function (): void {
    it('refuses to resolve its mapper and names the model', function (): void {
        expect(fn () => (new ModelWithoutEntityTestModel)->entityMapper())
            ->toThrow(
                EntityClassNotAvailable::class,
                'Entity class for '.ModelWithoutEntityTestModel::class.' not available.',
            );
    });

    it('tells how to fix it', function (): void {
        expect(fn () => (new ModelWithoutEntityTestModel)->entityMapper())
            ->toThrow(EntityClassNotAvailable::class, '$entityClassName');
    });

    it('refuses to build an entity for the same reason', function (): void {
        expect(fn () => (new ModelWithoutEntityTestModel)->toEntity())
            ->toThrow(EntityClassNotAvailable::class);
    });
});

describe('EntityClassNotAvailable', function (): void {
    it('is an exception that names the model class', function (): void {
        $exception = EntityClassNotAvailable::make('App\\Models\\Account');

        expect($exception)->toBeInstanceOf(Exception::class)
            ->and($exception->getMessage())->toBe(
                'Entity class for App\\Models\\Account not available. Please add the entity class name to $entityClassName property on model.',
            );
    });
});
