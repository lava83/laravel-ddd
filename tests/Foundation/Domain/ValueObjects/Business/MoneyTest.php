<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Business\Money;

describe('string representation', function (): void {
    it('keeps the decimals of the amount', function (): void {
        expect((string) new Money(12.5, 'EUR'))->toBe('12.50 €');
    });

    it('builds the amount from cents', function (): void {
        expect((string) Money::euros(1250))->toBe('12.50 €')
            ->and((string) Money::dollars(5))->toBe('0.05 $')
            ->and((string) Money::pounds(99))->toBe('0.99 £');
    });

    it('always shows two decimals', function (): void {
        expect((string) new Money(0, 'EUR'))->toBe('0.00 €')
            ->and((string) new Money(7, 'USD'))->toBe('7.00 $');
    });

    it('rounds float noise to the cent', function (): void {
        expect((string) new Money(0.1 + 0.2, 'EUR'))->toBe('0.30 €');
    });

    it('uses a dot as decimal separator whatever the locale', function (): void {
        $previous = setlocale(LC_NUMERIC, null);

        try {
            setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German');

            expect((string) new Money(12.5, 'EUR'))->toBe('12.50 €');
        } finally {
            setlocale(LC_NUMERIC, $previous === false ? 'C' : $previous);
        }
    });
});
