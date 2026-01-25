<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

final class NotEqual extends Equal
{
    // @mago-expect analyzer:unused-property
    protected FilterType $type = FilterType::NotEqual;
}
