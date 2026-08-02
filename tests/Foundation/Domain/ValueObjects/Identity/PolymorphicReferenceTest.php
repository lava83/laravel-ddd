<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\PolymorphicReference;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\PrefixedTestId;

describe('PolymorphicReference', function (): void {
    it('exposes its alias and id', function (): void {
        $id = PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');
        $ref = PolymorphicReference::of('user', $id);

        expect($ref->alias())->toBe('user')
            ->and($ref->id())->toBeInstanceOf(Id::class)
            ->and($ref->id())->toBe($id);
    });

    it('stringifies as alias:id', function (): void {
        $ref = PolymorphicReference::of('user', PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b'));

        expect((string) $ref)->toBe('user:018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');
    });

    it('serializes a uuid reference with a string id', function (): void {
        $ref = PolymorphicReference::of('user', PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b'));

        expect($ref->jsonSerialize())->toBe([
            'type' => 'user',
            'id' => '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b',
        ]);
    });

    it('serializes an integer reference with an int id', function (): void {
        $ref = PolymorphicReference::of('order', IntegerTestId::fromInt(42));

        expect($ref->jsonSerialize())->toBe([
            'type' => 'order',
            'id' => 42,
        ]);
    });

    it('is equal when alias and id both match', function (): void {
        $a = PolymorphicReference::of('user', PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b'));
        $b = PolymorphicReference::of('user', PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b'));

        expect($a->equals($b))->toBeTrue();
    });

    it('is not equal when the alias differs', function (): void {
        $id = PrefixedTestId::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

        expect(PolymorphicReference::of('user', $id)->equals(PolymorphicReference::of('admin', $id)))
            ->toBeFalse();
    });

    it('is not equal when the id differs', function (): void {
        expect(PolymorphicReference::of('order', IntegerTestId::fromInt(42))->equals(PolymorphicReference::of('order', IntegerTestId::fromInt(43))))
            ->toBeFalse();
    });
});
