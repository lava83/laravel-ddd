<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort;

use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;

/**
 * One order column of a {@see Sort}: a column of the list model and its
 * direction.
 */
final readonly class SortField
{
    public function __construct(
        private string $column,
        private SortDirection $direction = SortDirection::Ascending,
    ) {}

    public function column(): string
    {
        return $this->column;
    }

    public function direction(): SortDirection
    {
        return $this->direction;
    }

    /**
     * The field as a client requests it: `created_at`, or `-created_at` for
     * descending.
     */
    public function toString(): string
    {
        return ($this->direction === SortDirection::Descending ? '-' : '').$this->column;
    }
}
