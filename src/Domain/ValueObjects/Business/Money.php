<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Business;

use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Money extends ValueObject
{
    private const SYMBOLS = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];

    final public function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return sprintf('%.2F %s', $this->amount, $this->currencySymbol());
    }

    public static function euros(int $amount): static
    {
        return new static($amount / 100, 'EUR');
    }

    public static function dollars(int $amount): static
    {
        return new static($amount / 100, 'USD');
    }

    public static function pounds(int $amount): static
    {
        return new static($amount / 100, 'GBP');
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function currencySymbol(): string
    {
        return self::SYMBOLS[$this->currency];
    }

    /**
     * @return array<string, float|string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, float|string>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'symbol' => $this->currencySymbol(),
        ];
    }

    public function isEqual(Money $other): bool
    {
        return $this->currency === $other->currency
            && (int) round($this->amount * 100) === (int) round($other->amount * 100);
    }

    protected function validate(): array
    {
        $errors = [];

        if ($this->amount < 0) {
            $errors['amount'][] = 'Amount cannot be negative';
        }

        if (! array_key_exists($this->currency, self::SYMBOLS)) {
            $errors['currency'][] = 'Unsupported currency';
        }

        return $errors;
    }
}
