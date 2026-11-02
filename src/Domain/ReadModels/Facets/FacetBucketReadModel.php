<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ReadModels\Facets;

use Lava83\LaravelDdd\Domain\ReadModels\ReadModel;

/**
 * How many rows of a list carry one value of a facet. A NULL value is a
 * bucket of its own (`value: null`).
 */
final class FacetBucketReadModel extends ReadModel
{
    public function __construct(
        private readonly string|int|float|bool|null $value,
        private readonly int $count,
    ) {}

    public function value(): string|int|float|bool|null
    {
        return $this->value;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return array{value: string|int|float|bool|null, count: int}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'count' => $this->count,
        ];
    }
}
