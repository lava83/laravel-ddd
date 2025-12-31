<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class Like extends Filter
{
    protected FilterType $type = FilterType::Like;

    public function __construct(
        protected readonly string $target,
        protected readonly string|int|float|bool $value,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): string|int|float|bool
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
            ],
        ]);

        return !$validator->fails();
    }
}
