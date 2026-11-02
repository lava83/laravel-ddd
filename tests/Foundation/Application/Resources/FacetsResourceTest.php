<?php

declare(strict_types=1);

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Application\Resources\FacetsResource;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetBucketReadModel;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;

/**
 * @return Collection<int, FacetReadModel>
 */
function facetsResourceFacets(): Collection
{
    return new Collection([
        new FacetReadModel('type', [new FacetBucketReadModel(2, 32), new FacetBucketReadModel(1, 98)]),
        new FacetReadModel('status', [new FacetBucketReadModel(1, 120), new FacetBucketReadModel(null, 3)]),
        new FacetReadModel('empty'),
    ]);
}

/**
 * A page of three items out of more, so the cursor meta has a next cursor.
 */
function facetsResourceList(): JsonResource
{
    return JsonResource::collection(new CursorPaginator(
        [['id' => 1], ['id' => 2], ['id' => 3]],
        2,
        options: ['path' => '/list', 'parameters' => ['id']],
    ));
}

describe('FacetsResource', function (): void {
    it('renders one entry per facet, in the order of the facets, with buckets ordered by value', function (): void {
        $rendered = FacetsResource::make(facetsResourceFacets())->resolve();

        expect(array_keys($rendered))->toBe(['type', 'status', 'empty'])
            ->and($rendered['type'])->toBe([['value' => 1, 'count' => 98], ['value' => 2, 'count' => 32]])
            ->and($rendered['status'])->toBe([['value' => null, 'count' => 3], ['value' => 1, 'count' => 120]])
            ->and($rendered['empty'])->toBe([]);
    });

    it('renders no facets as an empty array', function (): void {
        expect(FacetsResource::make(new Collection)->resolve())->toBe([]);
    });

    it('adds the facets to meta, next to the cursor meta', function (): void {
        $list = FacetsResource::addTo(facetsResourceList(), ['status'], fn () => facetsResourceFacets());

        $meta = $list->toResponse(request())->getData(true)['meta'];

        expect($meta)->toHaveKeys(['path', 'per_page', 'next_cursor', 'prev_cursor', 'facets'])
            ->and($meta['next_cursor'])->toBeString()
            ->and(array_keys($meta['facets']))->toBe(['type', 'status', 'empty']);
    });

    it('neither counts nor adds facets when the client asked for none', function (): void {
        $list = facetsResourceList();
        $counted = false;

        $returned = FacetsResource::addTo($list, [], function () use (&$counted): Collection {
            $counted = true;

            return facetsResourceFacets();
        });

        expect($returned)->toBe($list)
            ->and($counted)->toBeFalse()
            ->and($list->toResponse(request())->getData(true)['meta'])->not->toHaveKey('facets');
    });

    it('keeps the additional data the list had', function (): void {
        $list = facetsResourceList()->additional(['meta' => ['hint' => 'kept'], 'extra' => true]);

        $body = FacetsResource::addTo($list, ['status'], fn () => facetsResourceFacets())->toResponse(request())->getData(true);

        expect($body['meta'])->toHaveKeys(['hint', 'facets', 'next_cursor'])
            ->and($body['extra'])->toBeTrue();
    });
});
