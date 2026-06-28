<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

interface FilterContract
{
    public function target(): string;

    /**
     * @return Collection<int, string|int|float|bool>|array<int, string|int|float|bool>|string|int|float|bool
     */
    public function value(): Collection|array|string|int|float|bool;

    public function type(): FilterType;

    /**
     * @return array{
     *     type: string,
     *     target: string,
     *     value: array<int, string|int|float|bool>|string|int|float|bool
     * }
     */
    public function toArray(): array;
}
