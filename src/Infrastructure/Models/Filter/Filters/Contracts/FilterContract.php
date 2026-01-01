<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

interface FilterContract
{
    public function target(): string;

    public function value(): Collection|array|string|int|float|bool;

    public function type(): FilterType;

    public function toArray(): array;
}
