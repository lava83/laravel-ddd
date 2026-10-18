<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

/**
 * A second identity type next to PlainTestId, to prove that two identities of
 * different types never equal each other, whatever they wrap.
 */
final class OtherPlainTestId extends Id
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
