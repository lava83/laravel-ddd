<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotValid;

/**
 * The order of a list: the fields a client asked for, then the key of the
 * list as the tie-breaker, so the order is unique, as cursor pagination needs.
 *
 * The request produces it and the read query applies it, so both use the one
 * key the request declares. Only columns of the list model are sortable, and
 * only columns that never hold NULL: Laravel's cursor pagination cannot page
 * over NULL.
 */
final readonly class Sort
{
    /**
     * @param  list<SortField>  $fields
     */
    private function __construct(
        private string $key,
        private array $fields,
    ) {}

    /**
     * The order of a list without a requested sort: the key ascending.
     */
    public static function byKey(string $key): self
    {
        return new self($key, []);
    }

    /**
     * Parse the fields a client asked for, e.g. `['-created_at', 'email']`.
     * A leading `-` means descending, and the first field sorts first.
     *
     * @param  array<array-key, mixed>  $fields
     * @param  list<string>  $allowed  the sortable columns of the list
     *
     * @throws SortNotValid
     */
    public static function fromArray(array $fields, array $allowed, string $key): self
    {
        $parsed = [];

        foreach ($fields as $field) {
            if (! is_string($field)) {
                throw SortNotValid::malformedField($field);
            }

            $column = str_starts_with($field, '-') ? substr($field, 1) : $field;

            if ($column === '') {
                throw SortNotValid::malformedField($field);
            }

            if (! in_array($column, $allowed, true)) {
                throw SortNotValid::unknownField($column, $allowed);
            }

            if (array_key_exists($column, $parsed)) {
                throw SortNotValid::duplicateField($column);
            }

            $parsed[$column] = new SortField(
                $column,
                $column === $field ? SortDirection::Ascending : SortDirection::Descending,
            );
        }

        return new self($key, array_values($parsed));
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * The fields the client asked for, without the key tie-breaker.
     *
     * @return list<SortField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The order columns as applied: the fields, then the key ascending. The
     * key is not repeated when the client sorted by it already.
     *
     * @return list<SortField>
     */
    public function orders(): array
    {
        foreach ($this->fields as $field) {
            if ($field->column() === $this->key) {
                return $this->fields;
            }
        }

        return [...$this->fields, new SortField($this->key)];
    }

    /**
     * The parameters a cursor of this order carries: one per order column,
     * named as the column.
     *
     * @return list<string>
     */
    public function cursorParameters(): array
    {
        return array_map(fn (SortField $field): string => $field->column(), $this->orders());
    }

    /**
     * Order `$query` by {@see self::orders()}, replacing any order it had. The
     * columns stay unqualified, so the cursor parameters are named exactly as
     * {@see self::cursorParameters()} says.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     */
    public function apply(EloquentBuilder $query): EloquentBuilder
    {
        $query->reorder();

        foreach ($this->orders() as $field) {
            $query->orderBy($field->column(), $field->direction()->value);
        }

        return $query;
    }
}
