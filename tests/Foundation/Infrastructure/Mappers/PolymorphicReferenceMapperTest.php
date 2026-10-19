<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\PolymorphicReference;
use Lava83\LaravelDdd\Infrastructure\Contracts\PolymorphicReferenceResolver;
use Lava83\LaravelDdd\Infrastructure\Mappers\PolymorphicReferenceMapper;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\PlainTestId;

beforeEach(function (): void {
    $this->mapper = new PolymorphicReferenceMapper;
    $this->mapper->register('order', fn (int|string $raw): IntegerTestId => IntegerTestId::fromInt((int) $raw));
    $this->mapper->register('user', fn (int|string $raw): PlainTestId => PlainTestId::fromValue($raw));
});

describe('resolver contract', function (): void {
    it('implements the resolver contract', function (): void {
        expect($this->mapper)->toBeInstanceOf(PolymorphicReferenceResolver::class);
    });
});

describe('fromPersistence', function (): void {
    it('builds the reference with the id of the factory registered for the alias', function (): void {
        $reference = $this->mapper->fromPersistence('order', 42);

        expect($reference)->toBeInstanceOf(PolymorphicReference::class)
            ->and($reference->alias())->toBe('order')
            ->and($reference->id())->toBeInstanceOf(IntegerTestId::class)
            ->and($reference->id()->value())->toBe(42);
    });

    it('hands the raw id to the factory unchanged', function (): void {
        expect($this->mapper->fromPersistence('user', 'abc')->id()->value())->toBe('abc')
            ->and($this->mapper->fromPersistence('order', '7')->id()->value())->toBe(7);
    });

    it('uses a different factory for a different alias', function (): void {
        expect($this->mapper->fromPersistence('order', 1)->id())->toBeInstanceOf(IntegerTestId::class)
            ->and($this->mapper->fromPersistence('user', 1)->id())->toBeInstanceOf(PlainTestId::class);
    });

    it('refuses an alias nobody registered and names it', function (): void {
        expect(fn () => $this->mapper->fromPersistence('invoice', 1))
            ->toThrow(InvalidArgumentException::class, 'No id factory registered for polymorphic alias: invoice');
    });

    it('lets a later registration replace an earlier one', function (): void {
        $this->mapper->register('order', fn (int|string $raw): PlainTestId => PlainTestId::fromValue('replaced-'.$raw));

        expect($this->mapper->fromPersistence('order', 5)->id()->value())->toBe('replaced-5');
    });
});

describe('toPersistence', function (): void {
    it('writes alias and id as strings', function (): void {
        $reference = PolymorphicReference::of('order', IntegerTestId::fromInt(42));

        expect($this->mapper->toPersistence($reference))->toBe(['type' => 'order', 'id' => '42']);
    });

    it('survives a round trip through the database columns', function (): void {
        $reference = $this->mapper->fromPersistence('user', 'abc');

        $columns = $this->mapper->toPersistence($reference);
        $again = $this->mapper->fromPersistence($columns['type'], $columns['id']);

        expect($again->equals($reference))->toBeTrue();
    });
});
