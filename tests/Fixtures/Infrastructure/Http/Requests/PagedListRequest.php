<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\PaginatesByCursor;

/**
 * Cursor pagination only, keyed by `account_id`.
 */
final class PagedListRequest extends FormRequest
{
    use PaginatesByCursor;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->cursorPaginationRules();
    }

    protected function listKey(): string
    {
        return 'account_id';
    }
}
