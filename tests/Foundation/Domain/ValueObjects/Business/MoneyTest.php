<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
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

describe('construction', function (): void {
    it('keeps amount and currency', function (): void {
        $money = new Money(12.5, 'EUR');

        expect($money->amount())->toBe(12.5)
            ->and($money->currency())->toBe('EUR');
    });

    it('accepts zero', function (): void {
        expect((new Money(0, 'USD'))->amount())->toBe(0.0);
    });

    it('accepts every supported currency', function (string $currency): void {
        expect((new Money(1, $currency))->currency())->toBe($currency);
    })->with(['USD', 'EUR', 'GBP']);

    it('rejects a negative amount', function (): void {
        expect(fn () => new Money(-0.01, 'EUR'))
            ->toThrow(ValidationException::class, 'Amount cannot be negative');
    });

    it('rejects an unsupported currency', function (): void {
        expect(fn () => new Money(1, 'CHF'))
            ->toThrow(ValidationException::class, 'Unsupported currency');
    });

    it('treats the currency code as case sensitive', function (): void {
        expect(fn () => new Money(1, 'eur'))
            ->toThrow(ValidationException::class, 'Unsupported currency');
    });

    it('rejects a negative amount of cents in the factories', function (): void {
        expect(fn () => Money::euros(-1))
            ->toThrow(ValidationException::class, 'Amount cannot be negative');
    });
});

describe('factories', function (): void {
    it('reads the argument as cents', function (): void {
        expect(Money::euros(1250)->amount())->toBe(12.5)
            ->and(Money::dollars(5)->amount())->toBe(0.05)
            ->and(Money::pounds(100)->amount())->toBe(1.0);
    });

    it('sets the currency', function (): void {
        expect(Money::euros(1)->currency())->toBe('EUR')
            ->and(Money::dollars(1)->currency())->toBe('USD')
            ->and(Money::pounds(1)->currency())->toBe('GBP');
    });
});

describe('symbols', function (): void {
    it('maps each currency to its symbol', function (): void {
        expect((new Money(1, 'EUR'))->currencySymbol())->toBe('€')
            ->and((new Money(1, 'USD'))->currencySymbol())->toBe('$')
            ->and((new Money(1, 'GBP'))->currencySymbol())->toBe('£');
    });
});

describe('serialisation', function (): void {
    it('exposes amount, currency and symbol as an array', function (): void {
        expect((new Money(12.5, 'EUR'))->toArray())->toBe([
            'amount' => 12.5,
            'currency' => 'EUR',
            'symbol' => '€',
        ]);
    });

    it('serialises to the same array as json', function (): void {
        $money = new Money(12.5, 'EUR');

        expect($money->jsonSerialize())->toBe($money->toArray())
            ->and(json_encode($money, JSON_UNESCAPED_UNICODE))->toBe('{"amount":12.5,"currency":"EUR","symbol":"€"}');
    });
});

describe('equality', function (): void {
    it('is equal when amount and currency match', function (): void {
        expect((new Money(12.5, 'EUR'))->isEqual(new Money(12.5, 'EUR')))->toBeTrue();
    });

    it('is not equal when the amount differs', function (): void {
        expect((new Money(12.5, 'EUR'))->isEqual(new Money(12.6, 'EUR')))->toBeFalse();
    });

    it('is not equal when the currency differs', function (): void {
        expect((new Money(12.5, 'EUR'))->isEqual(new Money(12.5, 'USD')))->toBeFalse();
    });
});

describe('rounding', function (): void {
    it('treats amounts that only differ by float noise as equal', function (): void {
        expect((new Money(0.1 + 0.2, 'EUR'))->isEqual(new Money(0.3, 'EUR')))->toBeTrue();
    });
});
