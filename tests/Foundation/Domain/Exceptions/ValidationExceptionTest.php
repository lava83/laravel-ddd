<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;

describe('ValidationException', function (): void {
    it('keeps the error bag keyed by property', function (): void {
        $exception = ValidationException::fromArray([
            'name' => ['Name is required'],
            'scopes' => ['Scopes must not be empty', 'Scopes must be unique'],
        ]);

        expect($exception->errors())->toBe([
            'name' => ['Name is required'],
            'scopes' => ['Scopes must not be empty', 'Scopes must be unique'],
        ]);
    });

    it('joins all messages into the exception message', function (): void {
        $exception = ValidationException::fromArray([
            'name' => ['Name is required'],
            'scopes' => ['Scopes must not be empty'],
        ]);

        expect($exception->getMessage())->toBe('Name is required Scopes must not be empty');
    });

    it('defaults to code 422', function (): void {
        expect(ValidationException::fromArray(['name' => ['Name is required']])->getCode())->toBe(422);
    });

    it('has an empty bag when built from a plain message', function (): void {
        $exception = new ValidationException('Title must be between 1 and 255 characters.');

        expect($exception->errors())->toBe([])
            ->and($exception->getMessage())->toBe('Title must be between 1 and 255 characters.');
    });

    it('builds an empty exception from an empty bag', function (): void {
        $exception = ValidationException::fromArray([]);

        expect($exception->errors())->toBe([])
            ->and($exception->getMessage())->toBe('');
    });
});
