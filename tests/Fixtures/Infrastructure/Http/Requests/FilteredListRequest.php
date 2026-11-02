<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;

/**
 * Client filters only.
 */
final class FilteredListRequest extends FormRequest
{
    use AcceptsFilters;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->filterRules();
    }
}
