<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

/**
 * Minimal concrete identity over the abstract Id base — it implements only the
 * two members ValueObject leaves abstract, so Id's own factories, value() and
 * equals() can be exercised directly, without any subclass overrides.
 */
final class PlainTestId extends Id
{
    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
