<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidCursor;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;

/**
 * The cursor pagination of a list: `per_page` and `cursor`.
 *
 * The cursor must carry the parameters of the order of the list: the key
 * alone, or with {@see AcceptsSort} the sort columns plus the key. A cursor
 * of another order fails validation, so the read query never throws
 * `UnexpectedValueException` for it.
 *
 * @phpstan-require-extends FormRequest
 */
trait PaginatesByCursor
{
    /**
     * The column the list is ordered by last, unique per row. Without
     * {@see AcceptsSort} it must be the column the read query orders by;
     * with it, the read query takes it from {@see Sort::key()}.
     */
    abstract protected function listKey(): string;

    protected function defaultPerPage(): int
    {
        return 50;
    }

    protected function maxPerPage(): int
    {
        return 200;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function cursorPaginationRules(): array
    {
        return [
            /**
             * The number of entries per page.
             *
             * @default 50
             */
            'per_page' => ['bail', 'nullable', 'integer', 'min:1', 'max:'.$this->maxPerPage()],
            /**
             * The cursor of the page to load (`next_cursor` or `prev_cursor` of the previous response), the first page
             * if omitted. It only fits the sort it was issued for.
             */
            'cursor' => ['bail', 'nullable', 'string', new ValidCursor($this->expectedCursorParameters())],
        ];
    }

    /**
     * The page size, the default for a missing or blank `per_page`.
     */
    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : $this->defaultPerPage();
    }

    /**
     * @return list<string>|null null if the sort is not valid: its own rule reports that
     */
    private function expectedCursorParameters(): ?array
    {
        // The seam to AcceptsSort, if the request uses it.
        if (method_exists($this, 'sortCursorParameters')) {
            return $this->sortCursorParameters();
        }

        return Sort::byKey($this->listKey())->cursorParameters();
    }
}
