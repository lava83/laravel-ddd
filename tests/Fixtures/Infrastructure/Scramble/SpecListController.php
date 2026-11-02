<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FilteredListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;

final class SpecListController
{
    public function full(FullListRequest $request, SpecListUseCase $useCase): AnonymousResourceCollection
    {
        return $useCase->execute(1, $request->perPage(), $request->sort(), $request->filters(), ...$request->facets());
    }

    public function filtered(FilteredListRequest $request): void {}
}
