<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Ramsey\Uuid\UuidInterface;

class MongoObjectId extends Id
{
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): int|string|UuidInterface
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    /**
     * @throws ValidationException
     */
    protected function validate(string $value): void
    {
        // ObjectId must be exactly 24 hexadecimal characters
        if (!preg_match('/^[a-f0-9]{24}$/i', $value)) {
            throw new ValidationException('Invalid ObjectId format. Expected 24 hexadecimal characters, got: '
            . $value);
        }
    }
}
