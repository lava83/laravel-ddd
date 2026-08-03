<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;

/**
 * Binds $modelClass, exercising the "defined" branch of the guard.
 */
final class BoundModelClassTestMapper extends ModelClassTestMapper
{
    protected static ?string $modelClass = EntityTestModel::class;
}
