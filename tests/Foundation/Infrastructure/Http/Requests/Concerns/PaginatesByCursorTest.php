<?php

declare(strict_types=1);

use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\PagedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\TunedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\UniqueSortListRequest;

describe('PaginatesByCursor per_page', function (): void {
    it('rejects a page size that is not an integer from 1 to 200', function (mixed $perPage): void {
        expect(ListRequests::errors(PagedListRequest::class, ['per_page' => $perPage]))->toHaveKey('per_page');
    })->with([0, 201, 'abc', '1.5']);

    it('accepts the bounds', function (int $perPage): void {
        expect(ListRequests::resolve(PagedListRequest::class, ['per_page' => $perPage])->perPage())->toBe($perPage);
    })->with([1, 200]);

    it('defaults to 50 for a missing or blank page size', function (array $query): void {
        expect(ListRequests::resolve(PagedListRequest::class, $query)->perPage())->toBe(50);
    })->with([
        'missing' => [[]],
        'blank' => [['per_page' => '']],
    ]);

    it('takes the default and the maximum of the request', function (): void {
        expect(ListRequests::resolve(TunedListRequest::class)->perPage())->toBe(20)
            ->and(ListRequests::resolve(TunedListRequest::class, ['per_page' => 30])->perPage())->toBe(30)
            ->and(ListRequests::errors(TunedListRequest::class, ['per_page' => 31]))->toHaveKey('per_page');
    });
});

describe('PaginatesByCursor cursor', function (): void {
    it('accepts a cursor of the key of the list', function (): void {
        $cursor = (new Cursor(['account_id' => 5]))->encode();

        expect(ListRequests::errors(PagedListRequest::class, ['cursor' => $cursor]))->toBe([]);
    });

    it('rejects a cursor that does not decode', function (): void {
        expect(ListRequests::errors(PagedListRequest::class, ['cursor' => 'garbage']))
            ->toBe(['cursor' => ['The cursor is not a valid cursor.']]);
    });

    it('rejects a cursor of another key', function (): void {
        $cursor = (new Cursor(['id' => 5]))->encode();

        expect(ListRequests::errors(PagedListRequest::class, ['cursor' => $cursor]))
            ->toBe(['cursor' => ['The cursor does not belong to the sort of the list.']]);
    });

    it('accepts a cursor of the requested sort', function (): void {
        $cursor = (new Cursor(['rank' => 2, 'name' => 'b', 'id' => 5]))->encode();

        expect(ListRequests::errors(FullListRequest::class, ['sort' => ['-rank', 'name'], 'cursor' => $cursor]))->toBe([]);
    });

    it('rejects a cursor of another sort', function (): void {
        $cursor = (new Cursor(['name' => 'b', 'id' => 5]))->encode();

        expect(ListRequests::errors(FullListRequest::class, ['sort' => ['rank'], 'cursor' => $cursor]))->toHaveKey('cursor');
    });

    it('rejects a cursor without the sort parameters', function (): void {
        $cursor = (new Cursor(['id' => 5]))->encode();

        expect(ListRequests::errors(FullListRequest::class, ['sort' => ['rank'], 'cursor' => $cursor]))->toHaveKey('cursor');
    });

    it('rejects a cursor of a sort when the list is sorted by its key', function (): void {
        $cursor = (new Cursor(['rank' => 2, 'id' => 5]))->encode();

        expect(ListRequests::errors(FullListRequest::class, ['cursor' => $cursor]))->toHaveKey('cursor');
    });

    it('takes a cursor that ends at a unique field or at the key', function (array $sort, array $parameters): void {
        $cursor = (new Cursor($parameters))->encode();

        expect(ListRequests::errors(UniqueSortListRequest::class, ['sort' => $sort, 'cursor' => $cursor]))->toBe([]);
    })->with([
        'a unique field' => [['email', '-rank'], ['email' => 'a@example.com']],
        'the key' => [['-id', 'rank'], ['id' => 5]],
    ]);

    it('rejects a cursor of 1.3, with the key after a unique field or fields after the key', function (array $sort, array $parameters): void {
        $cursor = (new Cursor($parameters))->encode();

        expect(ListRequests::errors(UniqueSortListRequest::class, ['sort' => $sort, 'cursor' => $cursor]))
            ->toBe(['cursor' => ['The cursor does not belong to the sort of the list.']]);
    })->with([
        'a unique field' => [['email'], ['email' => 'a@example.com', 'id' => 5]],
        'the key' => [['-id', 'rank'], ['id' => 5, 'rank' => 2]],
    ]);

    it('leaves an invalid sort to the sort rule and only checks that the cursor decodes', function (): void {
        $cursor = (new Cursor(['id' => 5]))->encode();

        expect(ListRequests::errors(FullListRequest::class, ['sort' => ['unknown'], 'cursor' => $cursor]))
            ->toHaveKey('sort.0')
            ->not->toHaveKey('cursor');
    });
});
