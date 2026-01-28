<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Business;

use InvalidArgumentException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Money extends ValueObject
{
    private readonly float $amount;

    private readonly string $currency;

    final public function __construct(float $amount, string $currency)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        if (!in_array($currency, ['USD', 'EUR', 'GBP'], true)) {
            throw new InvalidArgumentException('Unsupported currency');
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function __toString(): string
    {
        return sprintf('%d %s', $this->amount, $this->currencySymbol());
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
        return match ($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => throw new InvalidArgumentException('Unsupported currency'),
        };
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
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
