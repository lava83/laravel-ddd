<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use BackedEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The facets of a list a client asks for: `facets[]`, values of a
 * string-backed enum, each once.
 *
 * @template TFacet of BackedEnum
 *
 * @phpstan-require-extends FormRequest
 */
trait AcceptsFacets
{
    /**
     * @return class-string<TFacet>
     */
    abstract protected function facetEnum(): string;

    /**
     * @return array<string, list<mixed>>
     */
    protected function facetRules(): array
    {
        return [
            'facets' => ['bail', 'nullable', 'array'],
            /**
             * The attributes to count the entries of the whole list by, returned in `meta.facets`. A facet ignores the
             * filter on its own attribute. Ask for them with the first page only.
             */
            'facets.*' => ['bail', 'required', 'string', 'distinct', Rule::enum($this->facetEnum())],
        ];
    }

    /**
     * The facets the client asked for, in the order of the request; none for
     * a missing or blank `facets`.
     *
     * @return list<TFacet>
     */
    public function facets(): array
    {
        if (! $this->filled('facets')) {
            return [];
        }

        $enum = $this->facetEnum();

        return array_values(array_map(
            fn (string $value): BackedEnum => $enum::from($value),
            $this->array('facets'),
        ));
    }
}
