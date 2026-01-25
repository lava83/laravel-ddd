<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;
use Ramsey\Uuid\UuidInterface;

abstract class Id extends ValueObject
{
    final protected function __construct(
        protected readonly int|string|UuidInterface $value,
    ) {}

    public static function fromString(string $value): self
    {
        return new static($value);
    }

    public function equals(Id $other): bool
    {
        return $this->value->toString() === $other->value->toString();
    }
}
