<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts;

use Illuminate\Support\Collection;

/**
 * Anything the filter Builder can hold and serialise: built-in operators
 * ({@see FilterContract}) as well as custom filters, whose type is an
 * arbitrary string and therefore cannot be expressed as a FilterType.
 */
interface FilterDefinition
{
    /**
     * The operator string as it appears under the `type` key, e.g. `$eq` or a custom `$branchScope`.
     */
    public function identifier(): string;

    public function target(): ?string;

    /**
     * @return Collection<array-key, mixed>|array<array-key, mixed>|string|int|float|bool
     */
    public function value(): Collection|array|string|int|float|bool;

    /**
     * @return array{
     *     type: string,
     *     target?: string,
     *     value: array<array-key, mixed>|string|int|float|bool
     * }
     */
    public function toArray(): array;
}
