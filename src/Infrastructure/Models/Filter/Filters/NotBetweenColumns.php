<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

final class NotBetweenColumns extends BetweenColumns
{
    protected FilterType $type = FilterType::NotBetweenColumns;
}
