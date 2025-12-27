<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class GreaterThanEqualTo extends Filter
{
    protected FilterType $type = FilterType::GreaterThanEqualTo;

    public function __construct(
        protected readonly string $target,
        protected readonly int|float $value,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): int|float
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
