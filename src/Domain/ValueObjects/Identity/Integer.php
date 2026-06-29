<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

abstract class Integer extends Id
{
    public static function fromValue(UuidInterface|int|string $value): static
    {
        self::validate($value);

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

    protected static function validate(int|string|UuidInterface $value): void
    {
        if (blank($value)) {
            throw new InvalidArgumentException('Integer ID cannot be empty');
        }

        if (! is_int($value)) {
            throw new InvalidArgumentException('Integer ID can be only integer');
        }
    }
}
