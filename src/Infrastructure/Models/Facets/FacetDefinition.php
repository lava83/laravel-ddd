<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Facets;

/**
 * What {@see FacetCounter} counts for one facet of a list: a column of the
 * list model, or a column reached through a HasMany or BelongsToMany relation
 * of it.
 *
 * The filter target names the requested (client) filters the facet ignores,
 * so a tab counts what it would show with the other filters still applied.
 */
final readonly class FacetDefinition
{
    private function __construct(
        private string $key,
        private string $columnName,
        private ?string $relationName,
        private string $filterTarget,
    ) {}

    /**
     * Count the rows of the list model per value of `$column`. The filter
     * target defaults to `$column`.
     */
    public static function column(string $key, string $column, ?string $filterTarget = null): self
    {
        return new self($key, $column, null, $filterTarget ?? $column);
    }

    /**
     * Count the distinct rows of the list model per value of `$column` of a
     * HasMany or BelongsToMany relation: a row with two related values counts
     * once for each. The filter target defaults to `$relation`.
     */
    public static function relation(string $key, string $relation, string $column, ?string $filterTarget = null): self
    {
        return new self($key, $column, $relation, $filterTarget ?? $relation);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function columnName(): string
    {
        return $this->columnName;
    }

    public function relationName(): ?string
    {
        return $this->relationName;
    }

    public function filterTarget(): string
    {
        return $this->filterTarget;
    }
}
