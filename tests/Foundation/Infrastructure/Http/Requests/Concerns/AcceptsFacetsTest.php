<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FacetedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListTestFacet;

describe('AcceptsFacets', function (): void {
    it('returns the facets as enum cases in the order of the request', function (): void {
        expect(ListRequests::resolve(FacetedListRequest::class, ['facets' => ['type', 'status']])->facets())
            ->toBe([ListTestFacet::Type, ListTestFacet::Status]);
    });

    it('returns no facets for a missing or blank parameter', function (array $query): void {
        expect(ListRequests::resolve(FacetedListRequest::class, $query)->facets())->toBe([]);
    })->with([
        'missing' => [[]],
        'blank' => [['facets' => '']],
    ]);

    it('rejects facets on their own field', function (array $facets, string $field): void {
        expect(ListRequests::errors(FacetedListRequest::class, ['facets' => $facets]))->toHaveKey($field);
    })->with([
        'an unknown facet' => [['status', 'colour'], 'facets.1'],
        'a facet twice' => [['status', 'status'], 'facets.1'],
        'an empty facet' => [[''], 'facets.0'],
        'a nested array' => [[['status']], 'facets.0'],
    ]);

    it('rejects facets that are not an array', function (): void {
        expect(ListRequests::errors(FacetedListRequest::class, ['facets' => 'status']))->toHaveKey('facets');
    });
});
