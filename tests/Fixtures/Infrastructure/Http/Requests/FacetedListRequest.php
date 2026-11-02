<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFacets;

/**
 * Facets only.
 */
final class FacetedListRequest extends FormRequest
{
    /** @use AcceptsFacets<ListTestFacet> */
    use AcceptsFacets;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->facetRules();
    }

    protected function facetEnum(): string
    {
        return ListTestFacet::class;
    }
}
