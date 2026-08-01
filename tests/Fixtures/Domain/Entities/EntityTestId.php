<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

/**
 * Concrete UUID identity shared by the Entity fixtures.
 */
final class EntityTestId extends Uuid {}
