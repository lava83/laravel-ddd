<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use JsonSerializable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Stringable;

class MongoObjectId implements JsonSerializable, Stringable
{
    private function __construct(
        private readonly string $value
    ) {
        $this->validate($value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @return array{value: string}
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }

    private function validate(string $value): void
    {
        // ObjectId must be exactly 24 hexadecimal characters
        if (! preg_match('/^[a-f0-9]{24}$/i', $value)) {
            throw new ValidationException(
                'Invalid ObjectId format. Expected 24 hexadecimal characters, got: ' . $value
            );
        }
    }
}
