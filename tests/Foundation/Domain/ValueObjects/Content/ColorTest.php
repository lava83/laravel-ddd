<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Content\Color;
use Lava83\LaravelDdd\Domain\ValueObjects\Content\Enums\ColorEnum;

describe('construction', function (): void {
    it('accepts every colour of the enum', function (ColorEnum $case): void {
        expect(Color::fromString($case->value)->toString())->toBe($case->value);
    })->with(ColorEnum::cases());

    it('builds the same value through the constructor and the factory', function (): void {
        expect((string) new Color('red'))->toBe((string) Color::fromString('red'));
    });

    it('lower-cases and trims the input', function (): void {
        expect(Color::fromString('  Steel ')->toString())->toBe('steel')
            ->and(Color::fromString('MAGENTA')->toString())->toBe('magenta');
    });
});

// These pin the exception type as it is today: the Laravel validator throws its
// own ValidationException, not the Domain one. Adjust when validation moves
// into ValueObject::validate().
describe('rejection', function (): void {
    it('rejects a colour that is not in the enum', function (): void {
        expect(fn () => Color::fromString('chartreuse'))
            ->toThrow(ValidationException::class);
    });

    it('rejects an empty string', function (): void {
        expect(fn () => Color::fromString(''))
            ->toThrow(ValidationException::class);
    });

    it('rejects a string that only contains whitespace', function (): void {
        expect(fn () => Color::fromString('   '))
            ->toThrow(ValidationException::class);
    });

    it('does not accept a hex code', function (): void {
        expect(fn () => Color::fromString('#ff0000'))
            ->toThrow(ValidationException::class);
    });
});

describe('representation', function (): void {
    it('casts to its string value', function (): void {
        expect((string) Color::fromString('blue'))->toBe('blue');
    });

    it('serialises to its string value', function (): void {
        expect(Color::fromString('blue')->jsonSerialize())->toBe('blue')
            ->and(json_encode(['color' => Color::fromString('blue')]))->toBe('{"color":"blue"}');
    });
});
