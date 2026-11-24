<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * A sort with a nullable column, `score`, a relation field, `owner_name`, and
 * a unique column that may hold NULL, `email`.
 */
final class NullableSortListRequest extends FormRequest
{
    use AcceptsSort;
    use PaginatesByCursor;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->cursorPaginationRules(),
            ...$this->sortRules(),
        ];
    }

    protected function listKey(): string
    {
        return 'id';
    }

    protected function sortableFields(): array
    {
        return ['rank', 'score', 'email', 'owner_name'];
    }

    protected function uniqueSortableFields(): array
    {
        return ['email'];
    }

    protected function nullableSortableFields(): array
    {
        return ['score', 'email'];
    }

    protected function relationSortableFields(): array
    {
        return ['owner_name' => 'owner.name'];
    }
}
