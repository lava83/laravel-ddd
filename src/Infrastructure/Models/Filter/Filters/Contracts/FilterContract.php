<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts;

use Illuminate\Support\Collection;

interface FilterContract
{
    public function target(): string;

    public function value(): Collection|array|string;

    public function toArray(): array;
}
