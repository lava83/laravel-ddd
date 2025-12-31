<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Closure;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class IsNull extends Filter
{
    protected FilterType $type = FilterType::IsNull;

    public function __construct(
        protected readonly string $target,
        protected readonly bool $value = true,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function value(): bool
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
                    if ($value !== true) {
                        $fail("The {$attribute} must be true.");
                    }
                },
            ],
        ]);

        return !$validator->fails();
    }
}
