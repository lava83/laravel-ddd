<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;

/**
 * The facets of a list for `meta.facets`: one entry per facet key, each a
 * list of `{value, count}` ordered by value, the null bucket first. A value
 * without rows has no entry, which means a count of 0.
 *
 * @property-read Collection<int, FacetReadModel> $resource
 */
final class FacetsResource extends JsonResource
{
    /**
     * Add the facets to `meta.facets` of a list, next to the cursor meta, if
     * the client asked for any. Without facets, `$count` never runs, so no
     * facet query is made and the answer has no `meta.facets`.
     *
     * @template TCollection of ResourceCollection
     *
     * @param  TCollection  $collection
     * @param  array<array-key, mixed>  $facets  the facets the client asked for
     * @param  Closure(): Collection<int, FacetReadModel>  $count
     * @return TCollection
     */
    public static function addTo(ResourceCollection $collection, array $facets, Closure $count): ResourceCollection
    {
        if ($facets === []) {
            return $collection;
        }

        return $collection->additional(array_replace_recursive(
            $collection->additional,
            ['meta' => ['facets' => self::make($count())]],
        ));
    }

    /**
     * The shape is what makes Scramble document `meta.facets` correctly.
     *
     * @return array<string, list<array{value: string|int|float|bool|null, count: int}>>
     */
    public function toArray(Request $request): array
    {
        return $this->resource
            ->mapWithKeys(fn (FacetReadModel $facet): array => [$facet->key() => $facet->toArray()])
            ->all();
    }
}
