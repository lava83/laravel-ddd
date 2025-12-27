<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Closure;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

final class Equal extends Filter
{
    protected FilterType $type = FilterType::Equal;

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
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!is_string($value) && !is_int($value) && !is_float($value)) {
                        $fail("The {$attribute} must be a string, integer, or float.");
                    }
                },
            ],
        ]);

        return !$validator->fails();
    }
}
