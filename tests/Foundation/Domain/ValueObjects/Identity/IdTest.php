<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\PlainTestId;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

describe('Id', function (): void {
    it('stores and returns an int value without coercion', function (): void {
        $id = PlainTestId::fromValue(5);

        expect($id)->toBeInstanceOf(PlainTestId::class)
            ->and($id->value())->toBe(5);
    });

    it('stores and returns a string value', function (): void {
        expect(PlainTestId::fromValue('abc')->value())->toBe('abc');
    });

    it('stores and returns a uuid instance unchanged', function (): void {
        $uuid = RamseyUuid::uuid4();

        $id = PlainTestId::fromValue($uuid);

        expect($id->value())->toBeInstanceOf(UuidInterface::class)
            ->and($id->value())->toBe($uuid);
    });

    it('builds from a string via fromString without validation', function (): void {
        expect(PlainTestId::fromString('anything-goes')->value())->toBe('anything-goes');
    });

    it('is equal when the stringified values match', function (): void {
        expect(PlainTestId::fromValue('42')->equals(PlainTestId::fromValue('42')))->toBeTrue();
    });

    it('is not equal for different values', function (): void {
        expect(PlainTestId::fromValue(5)->equals(PlainTestId::fromValue(6)))->toBeFalse();
    });

    it('compares by string cast, so an int equals its numeric string', function (): void {
        expect(PlainTestId::fromValue(5)->equals(PlainTestId::fromValue('5')))->toBeTrue();
    });
});
