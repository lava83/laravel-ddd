<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\SortField;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestItemModel;

/**
 * @return list<string>
 */
function sortOrders(Sort $sort): array
{
    return array_map(fn (SortField $field): string => $field->toString(), $sort->orders());
}

describe('Sort', function (): void {
    it('orders by the key alone without fields', function (): void {
        $sort = Sort::byKey('account_id');

        expect($sort->key())->toBe('account_id')
            ->and($sort->fields())->toBe([])
            ->and(sortOrders($sort))->toBe(['account_id'])
            ->and($sort->cursorParameters())->toBe(['account_id']);
    });

    it('parses ascending and descending fields in order', function (): void {
        $sort = Sort::fromArray(['-rank', 'name'], ['rank', 'name'], 'id');

        expect($sort->fields())->toHaveCount(2)
            ->and($sort->fields()[0]->column())->toBe('rank')
            ->and($sort->fields()[0]->direction())->toBe(SortDirection::Descending)
            ->and($sort->fields()[1]->column())->toBe('name')
            ->and($sort->fields()[1]->direction())->toBe(SortDirection::Ascending);
    });

    it('appends the key ascending as the tie-breaker', function (): void {
        $sort = Sort::fromArray(['-rank'], ['rank'], 'id');

        expect(sortOrders($sort))->toBe(['-rank', 'id'])
            ->and($sort->cursorParameters())->toBe(['rank', 'id']);
    });

    it('does not repeat the key the client sorts by', function (): void {
        $sort = Sort::fromArray(['-id', 'rank'], ['rank', 'id'], 'id');

        expect(sortOrders($sort))->toBe(['-id', 'rank'])
            ->and($sort->cursorParameters())->toBe(['id', 'rank']);
    });

    it('rejects a field', function (array $fields, string $message): void {
        expect(fn () => Sort::fromArray($fields, ['rank', 'name'], 'id'))->toThrow(SortNotValid::class, $message);
    })->with([
        'not a string' => [[1], 'A sort field must be a column name, "-" in front for descending, got int.'],
        'empty' => [[''], 'A sort field must be a column name, "-" in front for descending, got "".'],
        'a lone minus' => [['-'], 'A sort field must be a column name, "-" in front for descending, got "-".'],
        'unknown' => [['email'], 'The list cannot be sorted by "email". Sortable: rank, name.'],
        'two minus' => [['--rank'], 'The list cannot be sorted by "-rank". Sortable: rank, name.'],
        'twice' => [['rank', 'rank'], 'The list is sorted by "rank" more than once.'],
        'in both directions' => [['-rank', 'rank'], 'The list is sorted by "rank" more than once.'],
    ]);

    it('applies its orders to a query, replacing the order it had', function (): void {
        $query = SortTestItemModel::query()->orderBy('name');

        $applied = Sort::fromArray(['-rank'], ['rank'], 'id')->apply($query);

        expect($applied)->toBe($query)
            ->and($query->getQuery()->orders)->toBe([
                ['column' => 'rank', 'direction' => 'desc'],
                ['column' => 'id', 'direction' => 'asc'],
            ]);
    });
});

describe('SortField', function (): void {
    it('prints itself as a client requests it', function (): void {
        expect((new SortField('rank'))->toString())->toBe('rank')
            ->and((new SortField('rank', SortDirection::Descending))->toString())->toBe('-rank');
    });
});
