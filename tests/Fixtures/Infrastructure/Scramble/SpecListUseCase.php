<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lava83\LaravelDdd\Application\Resources\FacetsResource;
use Lava83\LaravelDdd\Infrastructure\Http\ClientFilterErrors;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListTestFacet;

/**
 * The use case of the README example "A list endpoint".
 */
final readonly class SpecListUseCase
{
    public function __construct(
        private SpecListQuery $items,
    ) {}

    public function execute(int $branchId, int $perPage, Sort $sort, Builder $requested, ListTestFacet ...$facets): AnonymousResourceCollection
    {
        $scope = Builder::make()->eq('branch_id', $branchId);

        return ClientFilterErrors::translate(fn () => FacetsResource::addTo(
            SpecListItemResource::collection($this->items->page($scope->merge($requested), $sort, $perPage)),
            $facets,
            fn () => $this->items->facets($scope, $requested, ...$facets),
        ));
    }
}
