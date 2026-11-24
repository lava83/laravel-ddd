<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\SortField;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\MalformedRelationSortListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\NullableSortListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\SortedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\UniqueSortListRequest;

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

    it('sorts descending with a leading minus, ties by the key descending', function (): void {
        expect(requestedOrder(['sort' => ['-rank']]))->toBe(['-rank', '-id']);
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

    it('ends the sort at a unique field of the request', function (): void {
        $sort = ListRequests::resolve(UniqueSortListRequest::class, ['sort' => ['email', '-rank']])->sort();

        expect(array_map(fn (SortField $field): string => $field->toString(), $sort->orders()))->toBe(['email'])
            ->and($sort->cursorParameters())->toBe(['email']);
    });

    it('declares no unique field by default', function (): void {
        expect(requestedOrder(['sort' => ['name', '-rank']]))->toBe(['name', '-rank', '-id']);
    });

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

    it('carries the nullable columns and relation fields of the request', function (): void {
        $sort = ListRequests::resolve(NullableSortListRequest::class, ['sort' => ['-owner_name', 'score', 'rank']])->sort();
        [$owner, $score, $rank] = $sort->fields();

        expect([$owner->toString(), $owner->relation(), $owner->isNullable()])->toBe(['-owner_name', 'owner.name', true])
            ->and([$score->toString(), $score->relation(), $score->isNullable()])->toBe(['score', null, true])
            ->and([$rank->toString(), $rank->relation(), $rank->isNullable()])->toBe(['rank', null, false])
            ->and($sort->nullableCursorParameters())->toBe(['owner_name', 'score']);
    });

    it('does not end the sort at a unique field that may hold NULL', function (): void {
        $sort = ListRequests::resolve(NullableSortListRequest::class, ['sort' => ['email', 'rank']])->sort();

        expect($sort->cursorParameters())->toBe(['email', 'rank', 'id']);
    });

    it('declares no nullable column and no relation field by default', function (): void {
        $sort = ListRequests::resolve(SortedListRequest::class, ['sort' => ['rank']])->sort();

        expect($sort->nullableCursorParameters())->toBe([])
            ->and($sort->fields()[0]->relation())->toBeNull();
    });

    it('checks the declared relation fields on every request, with or without a sort', function (array $query): void {
        ListRequests::resolve(MalformedRelationSortListRequest::class, $query);
    })->with([
        'without a sort' => [[]],
        'with a sort by another field' => [['sort' => ['rank']]],
    ])->throws(RelationNotSortable::class, 'Sort field "owner_name": "owner" must be a relation of the list model and a column of it');

    it('accepts a relation field by its name and rejects its relation path', function (): void {
        expect(ListRequests::errors(NullableSortListRequest::class, ['sort' => ['-owner_name']]))->toBe([])
            ->and(ListRequests::errors(NullableSortListRequest::class, ['sort' => ['owner.name']]))->toHaveKey('sort.0');
    });
});
