<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class LessThan extends Filter
{
    protected FilterType $type = FilterType::LessThan;

    public function __construct(
        protected readonly string $target,
        protected readonly string|int|float $value,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): string|int|float
    {
        return $this->value;
    }

    protected function valueIsValid(): bool
    {
        $validator = validator([
            'value' => $this->value,
        ], [
            'value' => [
                'required',
                'numeric',
            ],
        ]);

        return !$validator->fails();
    }
}
