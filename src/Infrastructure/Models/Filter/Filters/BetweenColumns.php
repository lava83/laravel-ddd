<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class BetweenColumns extends Filter
{
    protected FilterType $type = FilterType::BetweenColumns;

    /**
     * @param array<int, string|int|float|bool> $value
     */
    public function __construct(
        protected readonly string $target,
        protected readonly array $value,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): array
    {
        return $this->value;
    }

    protected function valueIsValid(): bool
    {
        $validator = validator([
            'value' => $this->value,
        ], [
            'value' => [
                'array',
                'size:2',
            ],
            'value.*' => [
                'required',
                'string',
            ],
        ]);

        return !$validator->fails();
    }
}
