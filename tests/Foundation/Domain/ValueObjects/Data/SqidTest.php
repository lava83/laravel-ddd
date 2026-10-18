<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Data\Sqid;

describe('fromInts', function (): void {
    it('keeps the given integers in order', function (): void {
        expect(Sqid::fromInts(3, 1, 2)->ids())->toBe([3, 1, 2]);
    });

    it('accepts a single integer and zero', function (): void {
        expect(Sqid::fromInts(7)->ids())->toBe([7])
            ->and(Sqid::fromInts(0)->ids())->toBe([0]);
    });

    it('rejects an empty list', function (): void {
        expect(fn () => Sqid::fromInts())
            ->toThrow(ValidationException::class, 'Sqid requires at least one integer');
    });

    it('rejects a negative integer and names it', function (): void {
        expect(fn () => Sqid::fromInts(1, -5))
            ->toThrow(ValidationException::class, 'Sqid only supports non-negative integers, got: -5');
    });
});

describe('fromSqid', function (): void {
    it('decodes what it encoded', function (): void {
        $sqid = Sqid::fromInts(12, 34, 56);

        expect(Sqid::fromSqid($sqid->value())->ids())->toBe([12, 34, 56]);
    });

    it('rejects a string that does not decode', function (): void {
        expect(fn () => Sqid::fromSqid('!!!'))
            ->toThrow(ValidationException::class, 'Invalid or non-canonical sqid: !!!');
    });

    it('rejects an empty string', function (): void {
        expect(fn () => Sqid::fromSqid(''))
            ->toThrow(ValidationException::class);
    });
});

describe('representation', function (): void {
    it('encodes to a non-empty string', function (): void {
        expect(Sqid::fromInts(1)->value())->toBeString()->not->toBeEmpty();
    });

    it('uses the same string for value, toString, cast and json', function (): void {
        $sqid = Sqid::fromInts(42);

        expect($sqid->toString())->toBe($sqid->value())
            ->and((string) $sqid)->toBe($sqid->value())
            ->and($sqid->jsonSerialize())->toBe($sqid->value())
            ->and(json_encode(['id' => $sqid]))->toBe('{"id":"'.$sqid->value().'"}');
    });

    it('exposes the integers as a collection', function (): void {
        expect(Sqid::fromInts(1, 2)->collect()->all())->toBe([1, 2]);
    });
});

describe('equality', function (): void {
    it('is equal when the integers match', function (): void {
        expect(Sqid::fromInts(1, 2)->equals(Sqid::fromInts(1, 2)))->toBeTrue();
    });

    it('is not equal when the order differs', function (): void {
        expect(Sqid::fromInts(1, 2)->equals(Sqid::fromInts(2, 1)))->toBeFalse();
    });

    it('is not equal when the length differs', function (): void {
        expect(Sqid::fromInts(1)->equals(Sqid::fromInts(1, 1)))->toBeFalse();
    });

    it('is equal to its own decoded form', function (): void {
        $sqid = Sqid::fromInts(9, 8);

        expect($sqid->equals(Sqid::fromSqid($sqid->value())))->toBeTrue();
    });
});
