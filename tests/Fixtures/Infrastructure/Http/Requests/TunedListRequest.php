<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSearch;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * Overrides the page size and the length of the search phrase.
 */
final class TunedListRequest extends FormRequest
{
    use AcceptsSearch;
    use PaginatesByCursor;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [...$this->cursorPaginationRules(), ...$this->searchRules()];
    }

    protected function listKey(): string
    {
        return 'id';
    }

    protected function defaultPerPage(): int
    {
        return 20;
    }

    protected function maxPerPage(): int
    {
        return 30;
    }

    protected function searchFilterType(): string
    {
        return '$listTestSearch';
    }

    protected function maxSearchLength(): int
    {
        return 10;
    }
}
