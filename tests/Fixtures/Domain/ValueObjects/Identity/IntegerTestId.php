<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Integer;

/**
 * Concrete integer identity, so the abstract Integer base can be exercised.
 */
final class IntegerTestId extends Integer {}
