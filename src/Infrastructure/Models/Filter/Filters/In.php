<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Closure;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Override;

class In extends Filter
{
    protected FilterType $type = FilterType::In;

    public function __construct(
        protected readonly string $target,
        protected readonly array $value,
    ) {}

    #[Override]
    public function target(): string
    {
        return $this->target;
    }

    #[Override]
    public function value(): array
    {
        return $this->value;
    }

    #[Override]
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
