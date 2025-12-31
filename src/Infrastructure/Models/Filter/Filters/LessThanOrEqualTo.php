<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class LessThanOrEqualTo extends LessThan
{
    protected FilterType $type = FilterType::LessThanOrEqualTo;
}
