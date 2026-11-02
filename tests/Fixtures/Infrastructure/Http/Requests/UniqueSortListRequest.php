<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * A sort with a unique field, `email`, and the key among the sortable fields.
 */
final class UniqueSortListRequest extends FormRequest
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
        return ['rank', 'email', 'id'];
    }

    protected function uniqueSortableFields(): array
    {
        return ['email'];
    }
}
