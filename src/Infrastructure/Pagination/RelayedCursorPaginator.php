<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * Cursor paginator for a page that was read from another API: the cursors of
 * the neighbouring pages come from that API and are handed on unchanged.
 *
 * Laravel derives `next_cursor` / `prev_cursor` from the first and last item
 * and the ORDER BY column. Here there is no query and no sort column, so
 * nothing is derived from the items and they may be any object. The tokens are
 * opaque to this class; knowing their format is the job of whoever reads the
 * API response (see ApiCursorPageMapper).
 *
 * Counterpart of ReadModelCursorPaginator, which serves the producing side.
 *
 * @template TReadModel of object
 *
 * @extends CursorPaginator<int, TReadModel>
 */
class RelayedCursorPaginator extends CursorPaginator
{
    private readonly ?RelayedCursor $next;

    private readonly ?RelayedCursor $previous;

    /**
     * @param  iterable<int, TReadModel>  $items  already trimmed to the page size and in display order
     * @param  array<string, mixed>  $options  path, cursorName, query, fragment
     */
    public function __construct(
        iterable $items,
        int $perPage,
        ?string $nextCursor,
        ?string $prevCursor,
        array $options = [],
        ?Cursor $cursor = null,
    ) {
        parent::__construct($items, $perPage, $cursor, $options);

        $this->next = $nextCursor === null ? null : RelayedCursor::next($nextCursor);
        $this->previous = $prevCursor === null ? null : RelayedCursor::previous($prevCursor);
        $this->hasMore = $this->next !== null;
    }

    public function nextCursor(): ?Cursor
    {
        return $this->next;
    }

    public function previousCursor(): ?Cursor
    {
        return $this->previous;
    }

    /**
     * Answered from the tokens: the parent derives it from the current cursor,
     * and for a "previous" cursor it is always true.
     */
    public function hasMorePages(): bool
    {
        return $this->next !== null;
    }

    public function onFirstPage(): bool
    {
        return $this->previous === null;
    }

    /**
     * Items are already trimmed to the page size and in display order; the
     * parent implementation would trim and reverse them a second time.
     *
     * @param  iterable<int, TReadModel>|null  $items
     */
    protected function setItems($items): void
    {
        $this->items = (new Collection($items))->values();
    }
}
