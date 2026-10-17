<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * Cursor paginator whose items are read models, while the cursor values still
 * come from the Eloquent models they were mapped from.
 *
 * Laravel reads `next_cursor` / `prev_cursor` from the page items under the
 * ORDER BY column name. After mapping to read models that column may not exist
 * as a property (`account_id` vs. `id`). Each read model therefore remembers its
 * source model, and the cursor parameters are read from there.
 *
 * @template TModel of Model
 * @template TReadModel of object
 *
 * @extends CursorPaginator<int, TReadModel>
 */
class ReadModelCursorPaginator extends CursorPaginator
{
    /** @var WeakMap<object, TModel>|null */
    private ?WeakMap $sources = null;

    /**
     * Named `fromPage`, not `map`: a static `map()` would shadow the paginator's
     * `__call` forwarding to the collection, and Laravel itself calls
     * `$paginator->map()` when serialising resource collections.
     *
     * @template TMapModel of Model
     * @template TMapReadModel of object
     *
     * @param  CursorPaginator<int, TMapModel>  $page
     * @param  callable(TMapModel): TMapReadModel  $mapper
     * @return self<TMapModel, TMapReadModel>
     */
    public static function fromPage(CursorPaginator $page, callable $mapper): self
    {
        /** @var self<TMapModel, TMapReadModel> $paginator */
        $paginator = new self([], $page->perPage(), $page->cursor(), $page->getOptions());

        // Read the flag itself: hasMorePages() is always true for a "previous" cursor.
        $paginator->hasMore = $page->hasMore;
        $paginator->query = $page->query;

        $items = [];

        foreach ($page->items() as $model) {
            $readModel = $mapper($model);
            $paginator->sources()->offsetSet($readModel, $model);
            $items[] = $readModel;
        }

        $paginator->items = new Collection($items);

        return $paginator->withQueryString();
    }

    /**
     * Items are already trimmed to the page size and in display order (the
     * builder has reversed them for a "previous" cursor); the parent
     * implementation would trim and reverse them a second time.
     *
     * @param  iterable<int, TReadModel>|null  $items
     */
    protected function setItems($items): void
    {
        $this->items = new Collection($items);
    }

    /**
     * @param  mixed  $item
     * @return array<string, mixed>
     */
    public function getParametersForItem($item)
    {
        $key = $item instanceof JsonResource ? $item->resource : $item;

        $source = is_object($key) ? ($this->sources()[$key] ?? null) : null;

        return parent::getParametersForItem($source ?? $item);
    }

    /**
     * @return WeakMap<object, TModel>
     */
    private function sources(): WeakMap
    {
        return $this->sources ??= new WeakMap;
    }
}
