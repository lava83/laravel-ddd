<?php

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\NoFilterTypeDeclared;

final class Equal extends Filter
{
    protected ?FilterType $type = FilterType::Equal;

    /**
     * @throws NoFilterTypeDeclared
     */
    public function __construct(
        private readonly string $target,
        private readonly string $value,
    ) {
        parent::__construct();
    }

    public function target(): string
    {
        return $this->target;
    }

    public function value(): string
    {
        return $this->value;
    }
}
