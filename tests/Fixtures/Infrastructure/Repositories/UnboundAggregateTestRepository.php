<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories;

use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;

/**
 * Leaves $entityClassName unset so entityMapper() hits the not-configured guard.
 */
final class UnboundAggregateTestRepository extends Repository
{
    public function mapper(): EntityMapper
    {
        return $this->entityMapper();
    }
}
