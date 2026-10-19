<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use Ramsey\Uuid\UuidInterface;

abstract class Integer extends Id
{
    public static function new(): static
    {
        return new static(0);
    }

    public static function fromValue(UuidInterface|int|string $value): static
    {
        return new static($value);
    }

    public static function fromInt(int $value): static
    {
        return new static($value);
    }

    public function value(): int
    {
        return (int) $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function jsonSerialize(): int
    {
        return (int) $this->value;
    }

    protected function validate(): array
    {
        if (blank($this->value)) {
            return ['value' => ['Integer ID cannot be empty']];
        }

        if (! is_int($this->value)) {
            return ['value' => ['Integer ID can be only integer']];
        }

        return [];
    }
}
