<?php

declare(strict_types=1);

use Illuminate\Http\Resources\Json\JsonResource;
use Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions\CursorValueIsNull;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Infrastructure\Pagination\SortedCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestItemModel;

/**
 * @param  list<mixed>  $items
 * @return SortedCursorPaginator<int, mixed>
 */
function sortedPage(array $items): SortedCursorPaginator
{
    return new SortedCursorPaginator($items, 1, options: [
        'parameters' => ['owner_name', 'score', 'id'],
        'nullableParameters' => ['owner_name', 'score'],
        'parameterAttributes' => ['owner_name' => 'laravel_ddd_sort_owner_name'],
    ]);
}

describe('SortedCursorPaginator', function (): void {
    it('reads a parameter from its attribute, from an array, an object and a resource', function (mixed $item): void {
        expect(sortedPage([$item, $item])->nextCursor()?->toArray())
            ->toBe(['owner_name' => 'Ann', 'score' => 2, 'id' => 7, '_pointsToNextItems' => true]);
    })->with([
        'an array' => fn (): array => ['owner_name' => 'not this', 'laravel_ddd_sort_owner_name' => 'Ann', 'score' => 2, 'id' => 7],
        'a model' => fn (): SortTestItemModel => (new SortTestItemModel)->forceFill(['laravel_ddd_sort_owner_name' => 'Ann', 'score' => 2, 'id' => 7]),
        'a resource' => fn (): JsonResource => new JsonResource((object) ['laravel_ddd_sort_owner_name' => 'Ann', 'score' => 2, 'id' => 7]),
    ]);

    it('takes NULL for a nullable parameter', function (): void {
        $item = ['laravel_ddd_sort_owner_name' => null, 'score' => null, 'id' => 7];

        expect(sortedPage([$item, $item])->nextCursor()?->toArray())
            ->toBe(['owner_name' => null, 'score' => null, 'id' => 7, '_pointsToNextItems' => true]);
    });

    it('refuses NULL for any other parameter', function (): void {
        $item = ['laravel_ddd_sort_owner_name' => 'Ann', 'score' => 2, 'id' => null];

        sortedPage([$item, $item])->nextCursor();
    })->throws(CursorValueIsNull::class, 'The cursor parameter "id" is NULL');

    it('hands its options on to a page of read models', function (): void {
        $model = (new SortTestItemModel)->forceFill(['laravel_ddd_sort_owner_name' => null, 'score' => 3, 'id' => 7]);

        $page = ReadModelCursorPaginator::fromPage(sortedPage([$model, $model]), fn (SortTestItemModel $model): stdClass => (object) []);

        expect($page->nextCursor()?->toArray())->toBe(['owner_name' => null, 'score' => 3, 'id' => 7, '_pointsToNextItems' => true]);
    });
});
