<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\MongoObjectId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\PrefixedTestId;

describe('MongoObjectId', function (): void {
    it('builds from a 24-character hex string', function (): void {
        $id = MongoObjectId::fromString('507f1f77bcf86cd799439011');

        expect($id)->toBeInstanceOf(MongoObjectId::class)
            ->and($id->value())->toBe('507f1f77bcf86cd799439011');
    });

    it('renders the value through every string accessor', function (): void {
        $id = MongoObjectId::fromString('507f1f77bcf86cd799439011');

        expect($id->toString())->toBe('507f1f77bcf86cd799439011')
            ->and((string) $id)->toBe('507f1f77bcf86cd799439011')
            ->and($id->jsonSerialize())->toBe('507f1f77bcf86cd799439011');
    });

    it('is equal to another instance with the same value', function (): void {
        expect(MongoObjectId::fromString('507f1f77bcf86cd799439011')->equals(MongoObjectId::fromString('507f1f77bcf86cd799439011')))
            ->toBeTrue();
    });

    it('is not equal to an instance with a different value', function (): void {
        expect(MongoObjectId::fromString('507f1f77bcf86cd799439011')->equals(MongoObjectId::fromString('507f1f77bcf86cd799439012')))
            ->toBeFalse();
    });

    it('is not equal to an identity of a different type', function (): void {
        expect(MongoObjectId::fromString('507f1f77bcf86cd799439011')->equals(PrefixedTestId::fromString('11111111-1111-7111-8111-111111111111')))
            ->toBeFalse();
    });

    it('rejects a malformed objectid', function (): void {
        expect(fn () => MongoObjectId::fromString('not-hex'))
            ->toThrow(ValidationException::class, 'Invalid ObjectId format. Expected 24 hexadecimal characters, got: not-hex');
    });

    it('rejects an empty objectid', function (): void {
        expect(fn () => MongoObjectId::fromString(''))
            ->toThrow(ValidationException::class, 'Invalid ObjectId format.');
    });
});
