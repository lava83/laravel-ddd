<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Closure;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Override;

class NotIn extends In
{
    protected FilterType $type = FilterType::NotIn;
}
