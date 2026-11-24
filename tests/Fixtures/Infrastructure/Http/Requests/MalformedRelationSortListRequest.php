<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * A sort that declares a relation field without its column.
 */
final class MalformedRelationSortListRequest extends FormRequest
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
        return ['rank', 'owner_name'];
    }

    protected function relationSortableFields(): array
    {
        return ['owner_name' => 'owner'];
    }
}
