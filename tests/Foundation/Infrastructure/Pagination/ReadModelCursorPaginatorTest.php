<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Application\Resources\CursorTestResource;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\CursorTestReadModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\CursorTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\CursorTestOwnerModel;

beforeEach(function (): void {
    Schema::dropIfExists('cursor_test_items');
    Schema::dropIfExists('cursor_test_owners');

    Schema::create('cursor_test_owners', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Schema::create('cursor_test_items', function (Blueprint $table): void {
        $table->id('account_id');
        $table->unsignedBigInteger('owner_id');
    });

    CursorTestOwnerModel::query()->create(['name' => 'owner']);
});

function seedCursorItems(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        CursorTestModel::query()->create(['owner_id' => 1]);
    }
}

function cursorPage(int $perPage, ?string $cursor = null): ReadModelCursorPaginator
{
    $page = CursorTestModel::query()
        ->with('owner')
        ->orderBy('account_id')
        ->cursorPaginate($perPage, cursor: $cursor === null ? null : Cursor::fromEncoded($cursor));

    return ReadModelCursorPaginator::fromPage(
        $page,
        fn (CursorTestModel $m) => new CursorTestReadModel($m->account_id, $m->owner->name),
    );
}

/**
 * @return list<int>
 */
function pageIds(CursorPaginator $page): array
{
    return array_map(fn (CursorTestReadModel $r) => $r->id, $page->items());
}

describe('ReadModelCursorPaginator', function (): void {
    it('builds cursors although the sort column is not a read model property', function (): void {
        seedCursorItems(5);

        $middle = cursorPage(2, cursorPage(2)->nextCursor()?->encode());

        expect(pageIds($middle))->toBe([3, 4])
            ->and($middle->nextCursor())->not->toBeNull()
            ->and($middle->previousCursor())->not->toBeNull()
            ->and($middle->nextCursor()?->parameter('account_id'))->toBe(4)
            ->and($middle->previousCursor()?->parameter('account_id'))->toBe(3);
    });

    it('walks forward and backward through all pages without gaps or duplicates', function (): void {
        seedCursorItems(11);

        $forward = [];
        $page = cursorPage(4);
        $forward = [...$forward, ...pageIds($page)];

        while ($page->nextCursor() !== null) {
            $page = cursorPage(4, $page->nextCursor()->encode());
            $forward = [...$forward, ...pageIds($page)];
        }

        expect($forward)->toBe(range(1, 11));

        $backward = pageIds($page);

        while ($page->previousCursor() !== null) {
            $page = cursorPage(4, $page->previousCursor()->encode());
            $backward = [...pageIds($page), ...$backward];
        }

        expect($backward)->toBe(range(1, 11));
    });

    it('does not reverse a backward page twice', function (): void {
        seedCursorItems(6);

        $second = cursorPage(2, cursorPage(2)->nextCursor()?->encode());
        $back = cursorPage(2, $second->previousCursor()?->encode());

        expect(pageIds($back))->toBe([1, 2]);
    });

    it('has no previous cursor once a backward walk reaches the first page', function (): void {
        seedCursorItems(6);

        $second = cursorPage(2, cursorPage(2)->nextCursor()?->encode());
        $first = cursorPage(2, $second->previousCursor()?->encode());

        expect($first->previousCursor())->toBeNull()
            ->and($first->nextCursor())->not->toBeNull()
            ->and($first->onFirstPage())->toBeTrue();
    });

    it('handles an empty list', function (): void {
        $page = cursorPage(3);

        expect($page->items())->toBe([])
            ->and($page->nextCursor())->toBeNull()
            ->and($page->previousCursor())->toBeNull();
    });

    it('handles exactly one full page', function (): void {
        seedCursorItems(3);

        $page = cursorPage(3);

        expect(pageIds($page))->toBe([1, 2, 3])
            ->and($page->nextCursor())->toBeNull()
            ->and($page->hasMorePages())->toBeFalse();
    });

    it('has no next cursor on the last page', function (): void {
        seedCursorItems(5);

        $last = cursorPage(3, cursorPage(3)->nextCursor()?->encode());

        expect(pageIds($last))->toBe([4, 5])
            ->and($last->nextCursor())->toBeNull()
            ->and($last->previousCursor())->not->toBeNull();
    });

    it('keeps request query parameters in the links', function (): void {
        seedCursorItems(5);

        $this->app->instance('request', Request::create('/accounts', 'GET', ['filter' => 'active', 'per_page' => '2']));

        $page = cursorPage(2);

        expect($page->nextPageUrl())
            ->toContain('filter=active')
            ->toContain('per_page=2')
            ->toContain('cursor=');
    });

    it('keeps query parameters the source page was given via appends', function (): void {
        seedCursorItems(5);

        $source = CursorTestModel::query()->orderBy('account_id')->cursorPaginate(2)->appends(['sort' => 'name']);

        $page = ReadModelCursorPaginator::fromPage(
            $source,
            fn (CursorTestModel $m) => new CursorTestReadModel($m->account_id, 'x'),
        );

        expect($page->nextPageUrl())->toContain('sort=name');
    });

    it('rejects a cursor that does not match the ordering', function (): void {
        seedCursorItems(5);

        $foreign = (new Cursor(['name' => 'x'], true))->encode();

        expect(fn () => cursorPage(2, $foreign))->toThrow(UnexpectedValueException::class);
    });

    it('keeps eager loading intact', function (): void {
        seedCursorItems(5);

        DB::enableQueryLog();
        $page = cursorPage(5);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($page->items())->toHaveCount(5)
            ->and($queries)->toBe(2);
    });

    it('falls back to the item itself when it was never mapped', function (): void {
        seedCursorItems(3);

        $page = cursorPage(3);
        $foreign = (object) ['account_id' => 42];

        expect($page->getParametersForItem($foreign))->toBe(['account_id' => 42]);
    });

    it('keeps cursors valid after the collection is filtered', function (): void {
        seedCursorItems(5);

        $page = cursorPage(4);
        $page->setCollection($page->getCollection()->reject(fn (CursorTestReadModel $r) => $r->id === 4));

        expect(pageIds($page))->toBe([1, 2, 3])
            ->and($page->nextCursor()?->parameter('account_id'))->toBe(3);
    });

    it('resolves the source when items are wrapped in resources', function (): void {
        seedCursorItems(5);

        $page = cursorPage(2);
        $page->setCollection($page->getCollection()->map(fn (CursorTestReadModel $r) => new CursorTestResource($r)));

        expect($page->nextCursor()?->parameter('account_id'))->toBe(2);
    });

    it('is still a CursorPaginator', function (): void {
        seedCursorItems(1);

        expect(cursorPage(1))->toBeInstanceOf(CursorPaginator::class);
    });

    it('serialises through a resource collection with data, links and meta', function (): void {
        seedCursorItems(5);

        $body = CursorTestResource::collection(cursorPage(2))->toResponse(request())->getData(true);

        expect($body['data'])->toBe([['id' => 1, 'owner' => 'owner'], ['id' => 2, 'owner' => 'owner']])
            ->and($body['links'])->toHaveKeys(['first', 'last', 'prev', 'next'])
            ->and($body['meta'])->toHaveKeys(['path', 'per_page', 'next_cursor', 'prev_cursor'])
            ->and($body['meta']['per_page'])->toBe(2)
            ->and($body['meta']['next_cursor'])->toBeString()
            ->and($body['meta']['prev_cursor'])->toBeNull();
    });
});
