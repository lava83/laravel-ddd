<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Facets;

use Illuminate\Contracts\Database\Query\Expression;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\CustomFilterNotValid;
use LogicException;

/**
 * What {@see FacetCounter} counts for one facet of a list: a column of the
 * list model, a column reached through a HasMany or BelongsToMany relation of
 * it, or an SQL expression over the list model.
 *
 * The filter target names the requested (client) filters the facet ignores,
 * so a tab counts what it would show with the other filters still applied.
 * When the facet's own filter is a custom filter, the filter type names it
 * too: a client may send a custom filter without a target.
 */
final readonly class FacetDefinition
{
    /**
     * @param  list<string|int|float|bool|null>  $bindings
     */
    private function __construct(
        private string $key,
        private ?string $columnName,
        private ?string $relationName,
        private string $filterTarget,
        private ?string $filterType,
        private ?Expression $expression = null,
        private array $bindings = [],
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

    /**
     * Count the rows of the list model per value of an SQL expression, e.g. a
     * `CASE WHEN … END` over several columns. The filter target is required:
     * there is no column to default to.
     *
     * The expression is raw SQL and goes into the query as written. Build it
     * in code, never from client input, and pass every value that can vary as
     * a `?` placeholder with its binding instead of concatenating it.
     *
     * @param  list<string|int|float|bool|null>  $bindings  the values of the `?` placeholders, in order
     *
     * @throws CustomFilterNotValid when `$filterType` is a built-in operator
     */
    public static function expression(
        string $key,
        Expression $expression,
        string $filterTarget,
        ?string $filterType = null,
        array $bindings = [],
    ): self {
        return new self($key, null, null, $filterTarget, self::customType($filterType), $expression, $bindings);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @throws LogicException for an expression facet, which has no column
     */
    public function columnName(): string
    {
        if ($this->columnName === null) {
            throw new LogicException(sprintf('The facet "%s" counts an expression and has no column.', $this->key));
        }

        return $this->columnName;
    }

    /**
     * Named apart from the static factory, like columnName() and relationName().
     */
    public function rawExpression(): ?Expression
    {
        return $this->expression;
    }

    /**
     * @return list<string|int|float|bool|null>
     */
    public function bindings(): array
    {
        return $this->bindings;
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
