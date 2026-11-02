<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;

/**
 * Overrides validationData() and forgets withDecodedFilters(): its rules on
 * `filters.*` never see JSON filters.
 */
final class UnwiredValidationDataListRequest extends FormRequest
{
    use AcceptsFilters;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->filterRules();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        return $this->all();
    }
}
