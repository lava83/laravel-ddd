<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Facets;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\CustomFilterNotValid;

/**
 * What {@see FacetCounter} counts for one facet of a list: a column of the
 * list model, or a column reached through a HasMany or BelongsToMany relation
 * of it.
 *
 * The filter target names the requested (client) filters the facet ignores,
 * so a tab counts what it would show with the other filters still applied.
 * When the facet's own filter is a custom filter, the filter type names it
 * too: a client may send a custom filter without a target.
 */
final readonly class FacetDefinition
{
    private function __construct(
        private string $key,
        private string $columnName,
        private ?string $relationName,
        private string $filterTarget,
        private ?string $filterType,
    ) {}

    /**
     * Count the rows of the list model per value of `$column`. The filter
     * target defaults to `$column`.
     *
     * @throws CustomFilterNotValid when `$filterType` is a built-in operator
     */
    public static function column(string $key, string $column, ?string $filterTarget = null, ?string $filterType = null): self
    {
        return new self($key, $column, null, $filterTarget ?? $column, self::customType($filterType));
    }

    /**
     * Count the distinct rows of the list model per value of `$column` of a
     * HasMany or BelongsToMany relation: a row with two related values counts
     * once for each. The filter target defaults to `$relation`.
     *
     * @throws CustomFilterNotValid when `$filterType` is a built-in operator
     */
    public static function relation(string $key, string $relation, string $column, ?string $filterTarget = null, ?string $filterType = null): self
    {
        return new self($key, $column, $relation, $filterTarget ?? $relation, self::customType($filterType));
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

    public function filterType(): ?string
    {
        return $this->filterType;
    }

    /**
     * A built-in operator is dropped by its target already; as a filter type
     * it would drop nothing and hide the mistake.
     *
     * @throws CustomFilterNotValid
     */
    private static function customType(?string $type): ?string
    {
        if ($type !== null && FilterType::tryFrom($type) !== null) {
            throw CustomFilterNotValid::reservedType($type);
        }

        return $type;
    }
}
