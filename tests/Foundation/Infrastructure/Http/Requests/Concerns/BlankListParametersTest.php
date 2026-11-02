<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;

/*
 * Over HTTP, unlike the tests that resolve the request from the container:
 * the global middleware turns a blank element into null before the rules see
 * it.
 */
beforeEach(function (): void {
    Route::get('list', fn (FullListRequest $request): array => [
        'per_page' => $request->perPage(),
        'sort' => $request->sort()->cursorParameters(),
        'filters' => $request->filters()->toArray(),
        'facets' => $request->facets(),
    ]);
});

describe('Blank list parameters over HTTP', function (): void {
    it('rejects a blank element on its own field', function (string $query, string $field): void {
        $this->getJson('list?'.$query)
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);
    })->with([
        'a blank sort field' => ['sort[]=', 'sort.0'],
        'a blank sort field before a valid one' => ['sort[]=&sort[]=rank', 'sort.0'],
        'a blank facet' => ['facets[]=', 'facets.0'],
        'a blank facet before a valid one' => ['facets[]=&facets[]=status', 'facets.0'],
        'a blank filter' => ['filters[0]=', 'filters.0'],
    ]);

    it('reads a blank parameter as a missing one', function (string $query): void {
        $this->getJson('list?'.$query)
            ->assertOk()
            ->assertExactJson(['per_page' => 50, 'sort' => ['id'], 'filters' => [], 'facets' => []]);
    })->with([
        'per_page' => ['per_page='],
        'cursor' => ['cursor='],
        'sort' => ['sort='],
        'filters' => ['filters='],
        'search' => ['search='],
        'facets' => ['facets='],
    ]);
});
