<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\DistinctSortFields;
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
     * The columns of the list model a client may sort by. None of them may
     * hold NULL: Laravel's cursor pagination cannot page over NULL.
     *
     * @return list<string>
     */
    abstract protected function sortableFields(): array;

    /**
     * @return array<string, list<mixed>>
     */
    protected function sortRules(): array
    {
        return [
            /**
             * The fields to sort by, the first one first, `-` in front for descending (`sort[]=-created_at&sort[]=email`).
             * Ties are broken by the key of the list, which is also the order without `sort`.
             */
            'sort' => ['bail', 'array', 'list', 'min:1', new DistinctSortFields],
            'sort.*' => ['bail', 'required', 'string', Rule::in($this->sortOptions())],
        ];
    }

    /**
     * The requested sort, or the key ascending for a missing or blank `sort`.
     *
     * @throws SortNotValid
     */
    public function sort(): Sort
    {
        if (! $this->filled('sort')) {
            return Sort::byKey($this->listKey());
        }

        $fields = $this->input('sort');

        if (! is_array($fields)) {
            throw SortNotValid::malformedField($fields);
        }

        return Sort::fromArray($fields, $this->sortableFields(), $this->listKey());
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
