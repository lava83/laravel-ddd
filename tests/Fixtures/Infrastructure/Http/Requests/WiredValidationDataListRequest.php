<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;

/**
 * Overrides validationData() and passes its data through
 * withDecodedFilters(), as a request with its own validationData() must.
 */
final class WiredValidationDataListRequest extends FormRequest
{
    use AcceptsFilters;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            'filters.*.target' => ['string', 'not_in:secret'],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        return $this->withDecodedFilters($this->all());
    }
}
