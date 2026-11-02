<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSearch;

/**
 * A search without client filters.
 */
final class SearchedListRequest extends FormRequest
{
    use AcceptsSearch;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->searchRules();
    }

    protected function searchFilterType(): string
    {
        return '$listTestSearch';
    }
}
