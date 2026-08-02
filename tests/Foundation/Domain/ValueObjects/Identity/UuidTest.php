<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\PrefixedTestId;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

describe('Uuid', function (): void {
    describe('construction', function (): void {
        it('generates a version 7 uuid', function (): void {
            $uuid = Uuid::generate();

            expect($uuid)->toBeInstanceOf(Uuid::class)
                ->and($uuid->uuid())->toBeInstanceOf(UuidInterface::class)
                ->and($uuid->uuid()->getVersion())->toBe(7);
        });

        it('generates a distinct value on each call', function (): void {
            expect(Uuid::generate()->value())->not->toBe(Uuid::generate()->value());
        });

        it('returns the concrete subclass from its static factories', function (): void {
            expect(PrefixedTestId::generate())->toBeInstanceOf(PrefixedTestId::class)
                ->and(PrefixedTestId::fromString('11111111-1111-7111-8111-111111111111'))
                ->toBeInstanceOf(PrefixedTestId::class);
        });

        it('builds from a valid uuid string', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect($uuid->value())->toBe('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');
        });

        it('rejects a malformed string', function (): void {
            expect(fn () => Uuid::fromString('not-a-uuid'))
                ->toThrow(ValidationException::class, 'Invalid UUID format: not-a-uuid');
        });

        it('rejects an empty or blank string', function (): void {
            expect(fn () => Uuid::fromString(''))
                ->toThrow(ValidationException::class, 'Id cannot be empty')
                ->and(fn () => Uuid::fromString('   '))
                ->toThrow(ValidationException::class, 'Id cannot be empty');
        });

        it('round-trips through raw bytes', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect(strlen($uuid->bytes()))->toBe(16)
                ->and(Uuid::fromBytes($uuid->bytes())->value())->toBe($uuid->value());
        });

        it('copies an existing uuid into a new, equal instance', function (): void {
            $source = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');
            $copy = Uuid::fromUuid($source);

            expect($copy)->not->toBe($source)
                ->and($copy->equals($source))->toBeTrue();
        });
    });

    describe('fromValue', function (): void {
        it('builds from a valid uuid string', function (): void {
            expect(Uuid::fromValue('019fc26d-2e9c-73e8-9395-063403aa4dfb')->value())
                ->toBe('019fc26d-2e9c-73e8-9395-063403aa4dfb');
        });

        it('wraps a UuidInterface directly', function (): void {
            $native = RamseyUuid::fromString('019fc26d-2e9c-73e8-9395-063403aa4dfb');

            expect(Uuid::fromValue($native)->value())->toBe('019fc26d-2e9c-73e8-9395-063403aa4dfb');
        });

        it('builds from an integer via its 128-bit value', function (): void {
            expect(Uuid::fromValue(5)->value())->toBe('00000000-0000-0000-0000-000000000005');
        });

        it('returns the concrete subclass', function (): void {
            expect(PrefixedTestId::fromValue('019fc26d-2e9c-73e8-9395-063403aa4dfb'))
                ->toBeInstanceOf(PrefixedTestId::class);
        });

        it('throws a domain ValidationException for a malformed string', function (): void {
            expect(fn () => Uuid::fromValue('not-a-uuid'))
                ->toThrow(ValidationException::class, 'Invalid UUID format: not-a-uuid');
        });

        it('throws a domain ValidationException for an empty string', function (): void {
            expect(fn () => Uuid::fromValue(''))
                ->toThrow(ValidationException::class, 'Id cannot be empty');
        });
    });

    describe('equality', function (): void {
        it('is equal when the underlying value matches', function (): void {
            $value = '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b';

            expect(Uuid::fromString($value)->equals(Uuid::fromString($value)))->toBeTrue();
        });

        it('is not equal for different values', function (): void {
            $a = Uuid::fromString('00000000-0000-7000-8000-000000000000');
            $b = Uuid::fromString('ffffffff-ffff-7fff-bfff-ffffffffffff');

            expect($a->equals($b))->toBeFalse();
        });
    });

    describe('serialization', function (): void {
        it('renders the canonical string through every accessor', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect($uuid->value())->toBe('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b')
                ->and($uuid->toString())->toBe($uuid->value())
                ->and((string) $uuid)->toBe($uuid->value())
                ->and($uuid->jsonSerialize())->toBe($uuid->value());
        });

        it('exposes hex as the dashless lowercase form', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect(strlen($uuid->hex()))->toBe(32)
                ->and($uuid->hex())->toBe(str_replace('-', '', $uuid->value()));
        });

        it('has an id/hex array representation', function (): void {
            $uuid = Uuid::fromString('11111111-1111-7111-8111-111111111111');

            expect($uuid->toArray())->toBe([
                'id' => '11111111-1111-7111-8111-111111111111',
                'hex' => '11111111111171118111111111111111',
            ]);
        });
    });

    describe('comparison and predicates', function (): void {
        it('compares two uuids for sort order', function (): void {
            $a = Uuid::fromString('00000000-0000-7000-8000-000000000000');
            $b = Uuid::fromString('ffffffff-ffff-7fff-bfff-ffffffffffff');

            expect($a->compareTo($b))->toBeLessThan(0)
                ->and($b->compareTo($a))->toBeGreaterThan(0)
                ->and($a->compareTo($a))->toBe(0);
        });

        it('answers isBefore and isAfter', function (): void {
            $a = Uuid::fromString('00000000-0000-7000-8000-000000000000');
            $b = Uuid::fromString('ffffffff-ffff-7fff-bfff-ffffffffffff');

            expect($a->isBefore($b))->toBeTrue()
                ->and($a->isAfter($b))->toBeFalse()
                ->and($b->isAfter($a))->toBeTrue();
        });

        it('detects the nil uuid', function (): void {
            expect(Uuid::fromString('00000000-0000-0000-0000-000000000000')->isNil())->toBeTrue()
                ->and(Uuid::fromString('11111111-1111-7111-8111-111111111111')->isNil())->toBeFalse();
        });
    });

    describe('prefixing', function (): void {
        it('extracts the uuid from a prefixed id', function (): void {
            $value = '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b';

            expect(Uuid::fromPrefixed('entry_'.$value)->value())->toBe($value);
        });

        it('rejects a prefixed id without a separator', function (): void {
            expect(fn () => Uuid::fromPrefixed('nounderscore'))
                ->toThrow(ValidationException::class, 'Prefixed ID must contain underscore separator');
        });

        it('rejects a prefixed id whose body is not a uuid', function (): void {
            expect(fn () => Uuid::fromPrefixed('entry_notauuid'))
                ->toThrow(ValidationException::class, 'Invalid prefixed ID format');
        });

        it('extracts the prefix portion', function (): void {
            $value = '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b';

            expect(Uuid::extractPrefix('entry_'.$value))->toBe('entry');
        });

        it('validates a prefix without throwing', function (): void {
            $value = '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b';

            expect(Uuid::validatePrefix('entry_'.$value, 'entry'))->toBeTrue()
                ->and(Uuid::validatePrefix('entry_'.$value, 'other'))->toBeFalse()
                ->and(Uuid::validatePrefix('nounderscore', 'entry'))->toBeFalse();
        });
    });

    describe('display helpers', function (): void {
        it('shortens to the first eight characters', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect($uuid->shortId())->toBe('018f1a2b');
        });

        it('masks all but the first four characters for logging', function (): void {
            $uuid = Uuid::fromString('018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b');

            expect($uuid->logId())->toBe('018f****');
        });

        it('prepends the configured prefix', function (): void {
            $uuid = PrefixedTestId::fromString('11111111-1111-7111-8111-111111111111');

            expect($uuid->withPrefix())->toBe('entry_11111111-1111-7111-8111-111111111111');
        });

        it('renders an upper-cased, dash-separated display id', function (): void {
            $uuid = PrefixedTestId::fromString('11111111-1111-7111-8111-111111111111');

            expect($uuid->displayId())->toBe('ENTRY-11111111-1111-7111-8111-111111111111');
        });

        it('derives a reference number from the prefix and hex digits', function (): void {
            expect(PrefixedTestId::fromString('11111111-1111-7111-8111-111111111111')->referenceNumber())
                ->toBe('ENTRY-1111-1111')
                ->and(PrefixedTestId::fromString('abcdef12-3456-789a-8bcd-ef1234567890')->referenceNumber())
                ->toBe('ENTRY-0123-4512');
        });
    });

    describe('collections', function (): void {
        it('creates many distinct ids', function (): void {
            $ids = Uuid::createMany(3);

            expect($ids)->toHaveCount(3)
                ->and($ids[0])->toBeInstanceOf(Uuid::class)
                ->and(collect($ids)->map(fn (Uuid $id): string => $id->value())->unique())->toHaveCount(3);
        });

        it('maps an array of strings to ids and back', function (): void {
            $strings = [
                '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7b',
                '018f1a2b-3c4d-7e5f-8a1b-2c3d4e5f6a7c',
            ];

            $ids = Uuid::fromArray($strings);

            expect($ids)->toHaveCount(2)
                ->and($ids[0])->toBeInstanceOf(Uuid::class)
                ->and(Uuid::toStringArray($ids))->toBe($strings);
        });
    });
});
