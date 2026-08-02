<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;
use Ramsey\Uuid\Uuid as RamseyUuid;

describe('Integer', function (): void {
    describe('construction', function (): void {
        it('starts a new identity at zero', function (): void {
            expect(IntegerTestId::new()->value())->toBe(0);
        });

        it('builds from an int', function (): void {
            $id = IntegerTestId::fromInt(5);

            expect($id)->toBeInstanceOf(IntegerTestId::class)
                ->and($id->value())->toBe(5);
        });

        it('accepts an integer through fromValue, including zero', function (): void {
            expect(IntegerTestId::fromValue(0)->value())->toBe(0)
                ->and(IntegerTestId::fromValue(7)->value())->toBe(7);
        });

        it('rejects a non-integer value', function (): void {
            expect(fn () => IntegerTestId::fromValue('5'))
                ->toThrow(InvalidArgumentException::class, 'Integer ID can be only integer');
        });

        it('rejects an empty value', function (): void {
            expect(fn () => IntegerTestId::fromValue(''))
                ->toThrow(InvalidArgumentException::class, 'Integer ID cannot be empty');
        });

        it('rejects a uuid value', function (): void {
            expect(fn () => IntegerTestId::fromValue(RamseyUuid::uuid4()))
                ->toThrow(InvalidArgumentException::class, 'Integer ID can be only integer');
        });

        it('rejects a non-integer routed through the inherited fromString', function (): void {
            expect(fn () => IntegerTestId::fromString('abc'))
                ->toThrow(InvalidArgumentException::class, 'Integer ID can be only integer');
        });
    });

    describe('serialization', function (): void {
        it('returns an int from value() and jsonSerialize()', function (): void {
            $id = IntegerTestId::fromInt(42);

            expect($id->value())->toBe(42)
                ->and($id->jsonSerialize())->toBe(42);
        });

        it('casts to a numeric string', function (): void {
            expect((string) IntegerTestId::fromInt(42))->toBe('42');
        });
    });

    describe('equality', function (): void {
        it('is equal for the same integer', function (): void {
            expect(IntegerTestId::fromInt(5)->equals(IntegerTestId::fromInt(5)))->toBeTrue();
        });

        it('is not equal for different integers', function (): void {
            expect(IntegerTestId::fromInt(5)->equals(IntegerTestId::fromInt(6)))->toBeFalse();
        });
    });
});
