<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\DistinctSortFields;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;

/**
 * The sort of a list: `sort[]=-created_at&sort[]=email`, the first field
 * first, `-` in front for descending. The read query applies {@see self::sort()}
 * to its base query before `cursorPaginate()`.
 *
 * The allowed values are a `Rule::in()` on `sort.*`, so the OpenAPI spec lists
 * them. With {@see PaginatesByCursor}, the cursor must fit the requested sort.
 *
 * @phpstan-require-extends FormRequest
 */
trait AcceptsSort
{
    /**
     * The column the list is ordered by last, unique per row. {@see Sort}
     * carries it to the read query.
     */
    abstract protected function listKey(): string;

    /**
     * The fields a client may sort by: columns of the list model, and the
     * names of {@see self::relationSortableFields()}. A column that may hold
     * NULL belongs in {@see self::nullableSortableFields()} as well.
     *
     * @return list<string>
     */
    abstract protected function sortableFields(): array;

    /**
     * The sortable fields that are unique per row, e.g. `email` with a unique
     * index. A sort ends at the first of them, without the key after it, so
     * the database can serve the order from that index alone. Declare only
     * columns that are unique: the next page skips rows that tie with the
     * cursor row.
     *
     * @return list<string>
     */
    protected function uniqueSortableFields(): array
    {
        return [];
    }

    /**
     * The sortable columns that may hold NULL. NULL sorts as the greatest
     * value, and the read query has to page with `Sort::paginate()`: Laravel's
     * `cursorPaginate()` cannot page over NULL. A unique field that may hold
     * NULL does not end the sort.
     *
     * @return list<string>
     */
    protected function nullableSortableFields(): array
    {
        return [];
    }

    /**
     * The sortable fields that are a column of a BelongsTo or HasOne of the
     * list model, by name: `['personnel_number' => 'profile.personnel_number']`.
     * Clients send the name, never the relation. Such a field may be NULL, as
     * for a row without a related row, so the read query has to page with
     * `Sort::paginate()`.
     *
     * @return array<string, string>
     */
    protected function relationSortableFields(): array
    {
        return [];
    }

    /**
     * `filled`, not `required`, on `sort.*`: it fails a blank field as well,
     * and Scramble does not take it for a required `sort[]`.
     *
     * @return array<string, list<mixed>>
     */
    protected function sortRules(): array
    {
        return [
            /**
             * The fields to sort by, the first one first, `-` in front for descending (`sort[]=-created_at&sort[]=email`).
             * Ties are broken by the key of the list, in the direction of the last field. Without `sort`, the list is in
             * key order.
             */
            'sort' => ['bail', 'nullable', 'array', 'list', 'min:1', new DistinctSortFields],
            'sort.*' => ['bail', 'filled', 'string', Rule::in($this->sortOptions())],
        ];
    }

    /**
     * The requested sort, or the key ascending for a missing or blank `sort`.
     *
     * @throws SortNotValid
     * @throws RelationNotSortable a path of {@see self::relationSortableFields()} is not `relation.column`
     */
    public function sort(): Sort
    {
        // No fields for a missing sort, not Sort::byKey(): the declared relation fields are checked on every request.
        $fields = $this->filled('sort') ? $this->input('sort') : [];

        if (! is_array($fields)) {
            throw SortNotValid::malformedField($fields);
        }

        return Sort::fromArray(
            $fields,
            $this->sortableFields(),
            $this->listKey(),
            $this->uniqueSortableFields(),
            $this->nullableSortableFields(),
            $this->relationSortableFields(),
        );
    }

    /**
     * The parameters a cursor of the requested sort carries, for the cursor
     * rule of {@see PaginatesByCursor}.
     *
     * @return list<string>|null null if the sort is not valid: its own rule reports that
     */
    private function sortCursorParameters(): ?array
    {
        try {
            return $this->sort()->cursorParameters();
        } catch (SortNotValid) {
            return null;
        }
    }

    /**
     * Those of them that may be NULL, for the same rule.
     *
     * @return list<string> none if the sort is not valid
     */
    private function nullableSortCursorParameters(): array
    {
        try {
            return $this->sort()->nullableCursorParameters();
        } catch (SortNotValid) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function sortOptions(): array
    {
        $options = [];

        foreach ($this->sortableFields() as $field) {
            $options[] = $field;
            $options[] = '-'.$field;
        }

        return $options;
    }
}
