<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidCursor;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotApplicable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions\CursorValueIsNull;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestItemModel;

/**
 * One page of the sort test items, mapped to read models that only know their
 * id: the cursor must come from the source models. Through Laravel's
 * `cursorPaginate()` after `apply()`, or through `Sort::paginate()`.
 *
 * @return ReadModelCursorPaginator<SortTestItemModel, stdClass>
 */
function sortTestPage(Sort $sort, int $perPage, ?string $cursor = null, bool $paginate = false): ReadModelCursorPaginator
{
    return ReadModelCursorPaginator::fromPage(
        $paginate
            ? $sort->paginate(SortTestItemModel::query(), $perPage, cursor: $cursor)
            : $sort->apply(SortTestItemModel::query())->cursorPaginate($perPage, cursor: $cursor === null ? null : Cursor::fromEncoded($cursor)),
        fn (SortTestItemModel $model): stdClass => (object) ['id' => $model->id],
    );
}

/**
 * Walk all pages forward and return the ids page by page. Every cursor must
 * pass the cursor rule of the sort.
 *
 * @return list<list<int>>
 */
function sortTestWalk(Sort $sort, int $perPage, bool $paginate = false): array
{
    $pages = [];
    $cursor = null;

    do {
        if ($cursor !== null) {
            expect(validator(['cursor' => $cursor], ['cursor' => [new ValidCursor($sort->cursorParameters(), $sort->nullableCursorParameters())]])->passes())->toBeTrue();
        }

        $page = sortTestPage($sort, $perPage, $cursor, $paginate);
        $pages[] = array_map(fn (stdClass $item): int => $item->id, $page->items());
        $cursor = $page->nextCursor()?->encode();
    } while ($cursor !== null && count($pages) < 20);

    return $pages;
}

/**
 * Walk back from the last page and return the ids page by page, the first
 * page first.
 *
 * @return list<list<int>>
 */
function sortTestWalkBack(Sort $sort, int $perPage, bool $paginate = false): array
{
    $page = sortTestPage($sort, $perPage, paginate: $paginate);

    while ($page->nextCursor() !== null) {
        $page = sortTestPage($sort, $perPage, $page->nextCursor()->encode(), $paginate);
    }

    $backward = [array_map(fn (stdClass $item): int => $item->id, $page->items())];

    while ($page->previousCursor() !== null && count($backward) < 20) {
        $page = sortTestPage($sort, $perPage, $page->previousCursor()->encode(), $paginate);
        $backward[] = array_map(fn (stdClass $item): int => $item->id, $page->items());
    }

    return array_reverse($backward);
}

/**
 * id  rank  name  code  happened_at          score  status_id
 * 1   2     c     g     2026-01-02 10:00:00  null   1
 * 2   1     a     c     2026-01-01 10:00:00  5      1
 * 3   2     b     e     2026-01-02 10:00:00  7      2
 * 4   1     d     a     2026-01-01 10:00:00  1      2
 * 5   3     a     f     2026-01-03 10:00:00  3      1
 * 6   2     e     b     2026-01-02 10:00:00  9      3
 * 7   1     b     d     2026-01-01 10:00:00  2      1
 */
beforeEach(function (): void {
    Schema::dropIfExists('sort_test_items');
    Schema::create('sort_test_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('rank');
        $table->string('name');
        $table->string('code')->unique();
        $table->dateTime('happened_at');
        $table->unsignedInteger('score')->nullable();
        $table->unsignedInteger('status_id');
    });

    foreach ([
        [2, 'c', 'g', '2026-01-02 10:00:00', null, 1],
        [1, 'a', 'c', '2026-01-01 10:00:00', 5, 1],
        [2, 'b', 'e', '2026-01-02 10:00:00', 7, 2],
        [1, 'd', 'a', '2026-01-01 10:00:00', 1, 2],
        [3, 'a', 'f', '2026-01-03 10:00:00', 3, 1],
        [2, 'e', 'b', '2026-01-02 10:00:00', 9, 3],
        [1, 'b', 'd', '2026-01-01 10:00:00', 2, 1],
    ] as [$rank, $name, $code, $happenedAt, $score, $status]) {
        SortTestItemModel::query()->create(['rank' => $rank, 'name' => $name, 'code' => $code, 'happened_at' => $happenedAt, 'score' => $score, 'status_id' => $status]);
    }
});

describe('Sort with cursor pagination', function (): void {
    it('pages every row once, in the order of the sort', function (array $fields, array $order, bool $paginate): void {
        $sort = Sort::fromArray($fields, ['rank', 'name', 'code', 'happened_at', 'id'], 'id', unique: ['code']);

        foreach ([1, 2, 3] as $perPage) {
            expect(array_merge(...sortTestWalk($sort, $perPage, $paginate)))->toBe($order);
        }
    })->with([
        'by the key' => [[], [1, 2, 3, 4, 5, 6, 7]],
        'by the key descending' => [['-id', 'rank'], [7, 6, 5, 4, 3, 2, 1]],
        'a repeating column ascending' => [['rank'], [2, 4, 7, 1, 3, 6, 5]],
        'a repeating column descending, ties descending' => [['-rank'], [5, 6, 3, 1, 7, 4, 2]],
        'a string column descending' => [['-name'], [6, 4, 1, 7, 3, 5, 2]],
        'a datetime column ascending' => [['happened_at'], [2, 4, 7, 1, 3, 6, 5]],
        'a datetime column descending' => [['-happened_at'], [5, 6, 3, 1, 7, 4, 2]],
        'two columns in opposite directions' => [['-rank', 'name'], [5, 3, 1, 6, 2, 7, 4]],
        'a unique column' => [['code', '-rank'], [4, 6, 2, 7, 3, 5, 1]],
        'a unique column descending' => [['-code'], [1, 5, 3, 7, 2, 6, 4]],
    ])->with([
        "Laravel's cursorPaginate()" => [false],
        'Sort::paginate()' => [true],
    ]);

    it('pages back through the same pages', function (array $fields, bool $paginate): void {
        $sort = Sort::fromArray($fields, ['rank', 'name', 'code'], 'id', unique: ['code']);

        expect(sortTestWalkBack($sort, 2, $paginate))->toBe(sortTestWalk($sort, 2, $paginate));
    })->with([
        'two columns in opposite directions' => [['-rank', 'name']],
        'a column descending' => [['-rank']],
        'a unique column' => [['-code']],
    ])->with([
        "Laravel's cursorPaginate()" => [false],
        'Sort::paginate()' => [true],
    ]);

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

    it('fails loudly instead of paging over NULL in a column not declared nullable', function (bool $paginate): void {
        // Not declared nullable, the column is ordered as the database does: SQLite sorts NULL first ascending.
        $sort = Sort::fromArray(['score'], ['score'], 'id');

        sortTestPage($sort, 1, paginate: $paginate)->nextCursor();
    })->with([
        "Laravel's cursorPaginate()" => [false],
        'Sort::paginate()' => [true],
    ])->throws(CursorValueIsNull::class, 'The cursor parameter "score" is NULL');

    it('takes NULL in the cursor of the last field as the greatest value, not as no constraint', function (): void {
        // The cursor rule rejects such a cursor. Without the rule, the page must not start over inside the tie of rank 2.
        $sort = Sort::fromArray(['rank'], ['rank'], 'id');
        $page = $sort->paginate(SortTestItemModel::query(), 5, cursor: new Cursor(['rank' => 2, 'id' => null]));

        expect(array_map(fn (SortTestItemModel $model): int => $model->id, $page->items()))->toBe([5]);
    });

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

/*
 * The sort test items with NULL in score for four rows, and a second nullable
 * column, tag:
 *
 * id  rank  name  score  tag
 * 1   2     c     null   null
 * 2   1     a     5      a
 * 3   2     b     null   x
 * 4   1     d     1      null
 * 5   3     a     null   null
 * 6   2     e     9      b
 * 7   1     b     null   w
 */
describe('Sort with cursor pagination over a nullable column', function (): void {
    beforeEach(function (): void {
        Schema::table('sort_test_items', function (Blueprint $table): void {
            $table->string('tag')->nullable();
        });

        foreach ([1 => [null, null], 2 => [5, 'a'], 3 => [null, 'x'], 4 => [1, null], 5 => [null, null], 6 => [9, 'b'], 7 => [null, 'w']] as $id => [$score, $tag]) {
            DB::table('sort_test_items')->where('id', $id)->update(['score' => $score, 'tag' => $tag]);
        }
    });

    it('sorts NULL last ascending and first descending, and pages every row once in both directions', function (array $fields, array $order): void {
        $sort = Sort::fromArray($fields, ['rank', 'name', 'score', 'tag'], 'id', nullable: ['score', 'tag']);

        foreach ([1, 2, 3, 4, 7] as $perPage) {
            expect(array_merge(...sortTestWalk($sort, $perPage, paginate: true)))->toBe($order)
                ->and(array_merge(...sortTestWalkBack($sort, $perPage, paginate: true)))->toBe($order);
        }
    })->with([
        'ascending, NULL at the end' => [['score'], [4, 2, 6, 1, 3, 5, 7]],
        'descending, NULL at the start' => [['-score'], [7, 5, 3, 1, 6, 2, 4]],
        'NULL in the middle, after a column' => [['rank', 'score'], [4, 2, 7, 6, 1, 3, 5]],
        'NULL in the middle, descending after a column' => [['rank', '-score'], [7, 2, 4, 3, 1, 6, 5]],
        'NULL rows ordered by the next column' => [['score', 'name'], [4, 2, 6, 5, 3, 7, 1]],
        'NULL rows ordered by the next column descending' => [['-score', '-name'], [1, 7, 3, 5, 6, 2, 4]],
        'two nullable columns' => [['score', 'tag'], [4, 2, 6, 7, 3, 1, 5]],
        'two nullable columns descending' => [['-score', '-tag'], [5, 1, 3, 7, 6, 2, 4]],
    ]);

    it('pages a list of NULL only', function (): void {
        DB::table('sort_test_items')->update(['score' => null]);

        foreach ([1, 3] as $perPage) {
            expect(array_merge(...sortTestWalk(Sort::fromArray(['score'], ['score'], 'id', nullable: ['score']), $perPage, paginate: true)))->toBe([1, 2, 3, 4, 5, 6, 7])
                ->and(array_merge(...sortTestWalkBack(Sort::fromArray(['-score'], ['score'], 'id', nullable: ['score']), $perPage, paginate: true)))->toBe([7, 6, 5, 4, 3, 2, 1]);
        }
    });

    it('carries NULL in the cursor', function (): void {
        $page = sortTestPage(Sort::fromArray(['-score'], ['score'], 'id', nullable: ['score']), 2, paginate: true);

        expect($page->nextCursor()?->toArray())->toBe(['score' => null, 'id' => 5, '_pointsToNextItems' => true]);
    });

    it('fails loudly when the list query does not select a nullable sort column', function (): void {
        $sort = Sort::fromArray(['score'], ['score'], 'id', nullable: ['score']);

        // The rows of the page hold 1 and 5. Read as NULL, they would send the next page behind every value.
        $sort->paginate(SortTestItemModel::query()->select('id', 'rank'), 2)->nextCursor();
    })->throws(CursorValueIsNull::class, 'The cursor parameter "score" cannot be read: an item of the page has no "score".');

    it('does not end the order at a unique field that may hold NULL', function (): void {
        $sort = Sort::fromArray(['score'], ['score'], 'id', unique: ['score'], nullable: ['score']);

        expect($sort->cursorParameters())->toBe(['score', 'id'])
            ->and(array_merge(...sortTestWalk($sort, 2, paginate: true)))->toBe([4, 2, 6, 1, 3, 5, 7]);
    });

    it('cannot be applied for cursorPaginate()', function (): void {
        Sort::fromArray(['rank', '-score'], ['rank', 'score'], 'id', nullable: ['score'])->apply(SortTestItemModel::query());
    })->throws(SortNotApplicable::class, 'The sort by "score" may be NULL');

    it('applies a sort without a nullable field in the order of the request, although others are declared nullable', function (): void {
        $sort = Sort::fromArray(['-rank'], ['rank', 'score'], 'id', nullable: ['score']);

        expect(array_merge(...sortTestWalk($sort, 3)))->toBe([5, 6, 3, 1, 7, 4, 2]);
    });

    it('orders by the column alone on PostgreSQL, which sorts NULL as the greatest value already', function (): void {
        config()->set('database.connections.sort_pgsql', ['driver' => 'pgsql', 'database' => 'sort', 'host' => '127.0.0.1', 'username' => 'sort', 'password' => '']);
        $query = SortTestItemModel::on('sort_pgsql');

        DB::connection('sort_pgsql')->pretend(fn () => Sort::fromArray(['-score'], ['score'], 'id', nullable: ['score'])->paginate($query, 2));

        expect($query->toSql())->toBe('select * from "sort_test_items" order by "score" desc, "id" desc limit 3');
    });
});
