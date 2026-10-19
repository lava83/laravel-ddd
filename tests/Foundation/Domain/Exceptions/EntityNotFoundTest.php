<?php

declare(strict_types=1);

use Illuminate\Database\RecordsNotFoundException;
use Lava83\LaravelDdd\Domain\Exceptions\EntityNotFound;

describe('EntityNotFound', function (): void {
    it('has a default message', function (): void {
        expect((new EntityNotFound)->getMessage())->toBe('Entity not found');
    });

    it('takes a message of its own', function (): void {
        expect((new EntityNotFound('Account 7 not found'))->getMessage())->toBe('Account 7 not found');
    });

    it('is a RecordsNotFoundException, so the framework treats it as a 404', function (): void {
        expect(new EntityNotFound)->toBeInstanceOf(RecordsNotFoundException::class);
    });

    it('can be thrown and caught as such', function (): void {
        expect(fn () => throw new EntityNotFound)->toThrow(RecordsNotFoundException::class, 'Entity not found');
    });
});
