<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;

/**
 * A sort without cursor pagination.
 */
final class SortedListRequest extends FormRequest
{
    use AcceptsSort;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->sortRules();
    }

    protected function listKey(): string
    {
        return 'id';
    }

    protected function sortableFields(): array
    {
        return ['rank', 'name', 'id'];
    }
}
