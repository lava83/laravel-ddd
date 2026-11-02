<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListTestFacet;

interface SpecListQuery
{
    /**
     * @return CursorPaginator<int, SpecListItem>
     */
    public function page(Builder $filter, Sort $sort, int $perPage): CursorPaginator;

    /**
     * @return Collection<int, FacetReadModel>
     */
    public function facets(Builder $scope, Builder $requested, ListTestFacet ...$facets): Collection;
}
