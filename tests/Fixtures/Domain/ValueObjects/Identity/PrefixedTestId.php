<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

/**
 * Concrete UUID identity with a non-empty prefix, so the prefix-dependent
 * helpers (withPrefix(), referenceNumber(), displayId()) can be exercised.
 */
final class PrefixedTestId extends Uuid
{
    protected string $prefix = 'entry';
}
