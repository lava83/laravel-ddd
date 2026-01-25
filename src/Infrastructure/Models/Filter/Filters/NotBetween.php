<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

final class NotBetween extends Between
{
    // @mago-expect analyzer:unused-property
    protected FilterType $type = FilterType::NotBetween;
}
