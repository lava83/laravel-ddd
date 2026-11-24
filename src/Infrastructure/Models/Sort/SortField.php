<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort;

use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;

/**
 * One order column of a {@see Sort}: a column of the list model, or a column
 * of a to-one relation under a name of its own, and its direction.
 */
final readonly class SortField
{
    /**
     * @param  string  $column  the column of the list model, or the name of a relation field
     * @param  bool  $nullable  the column may hold NULL
     * @param  string|null  $relation  for a relation field: the relation and its column, `profile.personnel_number`
     */
    public function __construct(
        private string $column,
        private SortDirection $direction = SortDirection::Ascending,
        private bool $nullable = false,
        private ?string $relation = null,
    ) {}

    /**
     * The column of the list model, or the name of a relation field. The
     * cursor parameter of the field is named after it.
     */
    public function column(): string
    {
        return $this->column;
    }

    public function direction(): SortDirection
    {
        return $this->direction;
    }

    /**
     * True for a nullable column and for every relation field: a list row
     * without a related row sorts as NULL.
     */
    public function isNullable(): bool
    {
        return $this->nullable || $this->relation !== null;
    }

    /**
     * The relation and its column of a relation field, `profile.personnel_number`,
     * null for a column of the list model.
     */
    public function relation(): ?string
    {
        return $this->relation;
    }

    /**
     * The same field in the other direction.
     */
    public function reversed(): self
    {
        return new self(
            $this->column,
            $this->direction === SortDirection::Ascending ? SortDirection::Descending : SortDirection::Ascending,
            $this->nullable,
            $this->relation,
        );
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
