<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

final class Equal extends Filter
{
    protected FilterType $type = FilterType::Equal;

    public function __construct(
        protected readonly string $target,
        protected readonly string $value,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): string
    {
        return $this->value;
    }
}
