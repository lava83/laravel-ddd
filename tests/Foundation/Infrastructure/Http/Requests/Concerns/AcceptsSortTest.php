<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Sort\SortField;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\SortedListRequest;

/**
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function requestedOrder(array $query): array
{
    return array_map(
        fn (SortField $field): string => $field->toString(),
        ListRequests::resolve(SortedListRequest::class, $query)->sort()->orders(),
    );
}

describe('AcceptsSort', function (): void {
    it('sorts ascending, then by the key', function (): void {
        expect(requestedOrder(['sort' => ['rank']]))->toBe(['rank', 'id']);
    });

    it('sorts descending with a leading minus', function (): void {
        expect(requestedOrder(['sort' => ['-rank']]))->toBe(['-rank', 'id']);
    });

    it('sorts by several fields in the order of the request', function (): void {
        expect(requestedOrder(['sort' => ['-name', 'rank']]))->toBe(['-name', 'rank', 'id']);
    });

    it('does not repeat the key when the client sorts by it', function (): void {
        expect(requestedOrder(['sort' => ['rank', '-id']]))->toBe(['rank', '-id']);
    });

    it('sorts by the key ascending without a sort', function (array $query): void {
        expect(requestedOrder($query))->toBe(['id']);
    })->with([
        'missing' => [[]],
        'blank' => [['sort' => '']],
    ]);

    it('carries the key of the request', function (): void {
        expect(ListRequests::resolve(SortedListRequest::class)->sort()->key())->toBe('id');
    });

    it('rejects a sort on its own field', function (array $sort, string $field): void {
        expect(ListRequests::errors(SortedListRequest::class, ['sort' => $sort]))->toHaveKey($field);
    })->with([
        'an unknown field' => [['rank', 'email'], 'sort.1'],
        'an unknown field descending' => [['-email'], 'sort.0'],
        'a field twice' => [['rank', 'rank'], 'sort'],
        'a field in both directions' => [['rank', '-rank'], 'sort'],
        'an empty field' => [[''], 'sort.0'],
        'a lone minus' => [['-'], 'sort.0'],
        'two minus' => [['--rank'], 'sort.0'],
        'a comma list' => [['-rank,name'], 'sort.0'],
        'a nested array' => [[['rank']], 'sort.0'],
        'keyed fields' => [['a' => 'rank'], 'sort'],
    ]);

    it('rejects a sort that is not an array', function (): void {
        expect(ListRequests::errors(SortedListRequest::class, ['sort' => 'rank']))->toHaveKey('sort');
    });

    it('rejects an empty JSON list', function (): void {
        expect(ListRequests::errors(SortedListRequest::class, json: ['sort' => []]))->toHaveKey('sort');
    });

    it('names the duplicate field', function (): void {
        expect(ListRequests::errors(SortedListRequest::class, ['sort' => ['-name', 'name']]))
            ->toBe(['sort' => ['The sort holds "name" more than once.']]);
    });
});
