<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Enums\Communication\CountryAreaCode;

describe('values', function (): void {
    it('lists the dialling code of every case in declaration order', function (): void {
        expect(CountryAreaCode::values())->toBe(array_map(
            fn (CountryAreaCode $code): string => $code->value,
            CountryAreaCode::cases(),
        ));
    });

    it('lists unique dialling codes', function (): void {
        expect(CountryAreaCode::values())->toHaveCount(count(array_unique(CountryAreaCode::values())));
    });

    it('starts every dialling code with a plus sign', function (string $code): void {
        expect($code)->toStartWith('+');
    })->with(fn (): array => CountryAreaCode::values());
});

describe('lookup', function (): void {
    it('resolves a dialling code to its case', function (): void {
        expect(CountryAreaCode::from('+49'))->toBe(CountryAreaCode::DE)
            ->and(CountryAreaCode::tryFrom('+352'))->toBe(CountryAreaCode::LU);
    });

    it('does not know a dialling code that is not listed', function (): void {
        expect(CountryAreaCode::tryFrom('+81'))->toBeNull();
    });
});
