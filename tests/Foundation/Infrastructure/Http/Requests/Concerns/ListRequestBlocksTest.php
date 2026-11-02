<?php

declare(strict_types=1);

use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FacetedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FilteredListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListTestFacet;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\PagedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\SearchedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\SortedListRequest;

describe('List request blocks', function (): void {
    it('combines every block', function (): void {
        $request = ListRequests::resolve(FullListRequest::class, [
            'per_page' => 2,
            'cursor' => (new Cursor(['happened_at' => '2026-01-01 00:00:00', 'id' => 3]))->encode(),
            'filters' => '[{"type":"$in","target":"status_id","value":[1,2]}]',
            'sort' => ['-happened_at'],
            'search' => ' anna ',
            'facets' => ['status'],
        ]);

        expect($request->perPage())->toBe(2)
            ->and($request->sort()->cursorParameters())->toBe(['happened_at', 'id'])
            ->and($request->filters()->toArray())->toBe([
                ['type' => '$in', 'target' => 'status_id', 'value' => [1, 2]],
                ['type' => '$listTestSearch', 'value' => 'anna'],
            ])
            ->and($request->facets())->toBe([ListTestFacet::Status]);
    });

    it('reports the errors of every block at once', function (): void {
        expect(array_keys(ListRequests::errors(FullListRequest::class, [
            'per_page' => 0,
            'cursor' => 'garbage',
            'filters' => '{',
            'sort' => ['nope'],
            'search' => str_repeat('a', 101),
            'facets' => ['nope'],
        ])))->toEqualCanonicalizing(['per_page', 'cursor', 'filters', 'sort.0', 'search', 'facets.0']);
    });

    it('ignores the parameters of the blocks a request does not use', function (string $request, array $fields): void {
        expect(array_keys(ListRequests::errors($request, [
            'per_page' => 0,
            'cursor' => 'garbage',
            'filters' => '{',
            'sort' => ['nope'],
            'search' => ['nope'],
            'facets' => ['nope'],
        ])))->toEqualCanonicalizing($fields);
    })->with([
        'cursor only' => [PagedListRequest::class, ['per_page', 'cursor']],
        'filters only' => [FilteredListRequest::class, ['filters']],
        'sort only' => [SortedListRequest::class, ['sort.0']],
        'search only' => [SearchedListRequest::class, ['search']],
        'facets only' => [FacetedListRequest::class, ['facets.0']],
    ]);
});
