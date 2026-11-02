<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidCursor;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions\CursorValueIsNull;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestItemModel;

/**
 * One page of the sort test items, mapped to read models that only know their
 * id: the cursor must come from the source models.
 *
 * @return ReadModelCursorPaginator<SortTestItemModel, stdClass>
 */
function sortTestPage(Sort $sort, int $perPage, ?string $cursor = null): ReadModelCursorPaginator
{
    return ReadModelCursorPaginator::fromPage(
        $sort->apply(SortTestItemModel::query())->cursorPaginate($perPage, cursor: $cursor === null ? null : Cursor::fromEncoded($cursor)),
        fn (SortTestItemModel $model): stdClass => (object) ['id' => $model->id],
    );
}

/**
 * Walk all pages forward and return the ids page by page. Every cursor must
 * pass the cursor rule of the sort.
 *
 * @return list<list<int>>
 */
function sortTestWalk(Sort $sort, int $perPage): array
{
    $pages = [];
    $cursor = null;

    do {
        if ($cursor !== null) {
            expect(validator(['cursor' => $cursor], ['cursor' => [new ValidCursor($sort->cursorParameters())]])->passes())->toBeTrue();
        }

        $page = sortTestPage($sort, $perPage, $cursor);
        $pages[] = array_map(fn (stdClass $item): int => $item->id, $page->items());
        $cursor = $page->nextCursor()?->encode();
    } while ($cursor !== null && count($pages) < 20);

    return $pages;
}

/**
 * id  rank  name  happened_at          score  status_id
 * 1   2     c     2026-01-02 10:00:00  null   1
 * 2   1     a     2026-01-01 10:00:00  5      1
 * 3   2     b     2026-01-02 10:00:00  7      2
 * 4   1     d     2026-01-01 10:00:00  1      2
 * 5   3     a     2026-01-03 10:00:00  3      1
 * 6   2     e     2026-01-02 10:00:00  9      3
 * 7   1     b     2026-01-01 10:00:00  2      1
 */
beforeEach(function (): void {
    Schema::dropIfExists('sort_test_items');
    Schema::create('sort_test_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('rank');
        $table->string('name');
        $table->dateTime('happened_at');
        $table->unsignedInteger('score')->nullable();
        $table->unsignedInteger('status_id');
    });

    foreach ([
        [2, 'c', '2026-01-02 10:00:00', null, 1],
        [1, 'a', '2026-01-01 10:00:00', 5, 1],
        [2, 'b', '2026-01-02 10:00:00', 7, 2],
        [1, 'd', '2026-01-01 10:00:00', 1, 2],
        [3, 'a', '2026-01-03 10:00:00', 3, 1],
        [2, 'e', '2026-01-02 10:00:00', 9, 3],
        [1, 'b', '2026-01-01 10:00:00', 2, 1],
    ] as [$rank, $name, $happenedAt, $score, $status]) {
        SortTestItemModel::query()->create(['rank' => $rank, 'name' => $name, 'happened_at' => $happenedAt, 'score' => $score, 'status_id' => $status]);
    }
});

describe('Sort with cursor pagination', function (): void {
    it('pages every row once, in the order of the sort', function (array $fields, array $order): void {
        $sort = Sort::fromArray($fields, ['rank', 'name', 'happened_at'], 'id');

        foreach ([1, 2, 3] as $perPage) {
            expect(array_merge(...sortTestWalk($sort, $perPage)))->toBe($order);
        }
    })->with([
        'by the key' => [[], [1, 2, 3, 4, 5, 6, 7]],
        'a repeating column ascending' => [['rank'], [2, 4, 7, 1, 3, 6, 5]],
        'a repeating column descending' => [['-rank'], [5, 1, 3, 6, 2, 4, 7]],
        'a string column descending' => [['-name'], [6, 4, 1, 3, 7, 2, 5]],
        'a datetime column ascending' => [['happened_at'], [2, 4, 7, 1, 3, 6, 5]],
        'a datetime column descending' => [['-happened_at'], [5, 1, 3, 6, 2, 4, 7]],
        'two columns in opposite directions' => [['-rank', 'name'], [5, 3, 1, 6, 2, 7, 4]],
    ]);

    it('pages back through the same pages', function (): void {
        $sort = Sort::fromArray(['-rank', 'name'], ['rank', 'name'], 'id');
        $forward = sortTestWalk($sort, 2);

        $page = sortTestPage($sort, 2);

        while ($page->nextCursor() !== null) {
            $page = sortTestPage($sort, 2, $page->nextCursor()->encode());
        }

        $backward = [array_map(fn (stdClass $item): int => $item->id, $page->items())];

        while ($page->previousCursor() !== null) {
            $page = sortTestPage($sort, 2, $page->previousCursor()->encode());
            $backward[] = array_map(fn (stdClass $item): int => $item->id, $page->items());
        }

        expect(array_reverse($backward))->toBe($forward);
    });

    it('counts the same facets with and without a sort', function (): void {
        $count = fn (?Sort $sort): array => FacetCounter::count(
            fn (Builder $filter) => ($sort?->apply(SortTestItemModel::query()) ?? SortTestItemModel::query())->filter($filter->toArray()),
            Builder::make(),
            Builder::make(),
            FacetDefinition::column('status', 'status_id'),
        )->map(fn (FacetReadModel $facet): array => $facet->toArray())->all();

        $expected = [[['value' => 1, 'count' => 4], ['value' => 2, 'count' => 2], ['value' => 3, 'count' => 1]]];

        expect($count(null))->toBe($expected)
            ->and($count(Sort::fromArray(['-name'], ['name'], 'id')))->toBe($expected);
    });

    it('fails loudly instead of paging over NULL', function (): void {
        $sort = Sort::fromArray(['score'], ['score'], 'id');

        sortTestPage($sort, 1)->nextCursor();
    })->throws(CursorValueIsNull::class, 'The cursor parameter "score" is NULL');

    it('repeats rows when a datetime column holds fractional seconds', function (): void {
        // The trap the docs warn about: the cursor carries the cast value as `Y-m-d H:i:s`, without the fraction.
        // Ascending, the next page starts before the cursor row again; descending, it would skip rows.
        SortTestItemModel::query()->whereKey(2)->update(['happened_at' => '2026-01-01 10:00:00.500000']);
        $sort = Sort::fromArray(['happened_at'], ['happened_at'], 'id');

        $first = sortTestPage($sort, 3);
        $second = sortTestPage($sort, 3, $first->nextCursor()?->encode());

        expect(array_map(fn (stdClass $item): int => $item->id, $first->items()))->toBe([4, 7, 2])
            ->and(array_map(fn (stdClass $item): int => $item->id, $second->items()))->toBe([4, 7, 2]);
    });
});
