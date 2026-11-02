<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFacets;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSearch;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * Every block, plus a rule of its own on `filters.*`: the scope filter is a
 * filter of the model too, but only the use case may set it.
 */
final class FullListRequest extends FormRequest
{
    /** @use AcceptsFacets<ListTestFacet> */
    use AcceptsFacets;

    use AcceptsFilters;
    use AcceptsSearch;
    use AcceptsSort;
    use PaginatesByCursor;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->cursorPaginationRules(),
            ...$this->filterRules(),
            ...$this->sortRules(),
            ...$this->searchRules(),
            ...$this->facetRules(),
            'filters.*.target' => ['string', Rule::notIn(['$listTestScope'])],
        ];
    }

    protected function listKey(): string
    {
        return 'id';
    }

    protected function sortableFields(): array
    {
        return ['rank', 'name', 'happened_at'];
    }

    protected function searchFilterType(): string
    {
        return '$listTestSearch';
    }

    protected function facetEnum(): string
    {
        return ListTestFacet::class;
    }
}
