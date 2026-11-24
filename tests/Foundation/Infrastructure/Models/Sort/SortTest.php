<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotApplicable;
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

    it('appends the key as the tie-breaker, in the direction of the last field', function (array $fields, array $orders): void {
        $sort = Sort::fromArray($fields, ['rank', 'name'], 'id');

        expect(sortOrders($sort))->toBe($orders)
            ->and($sort->cursorParameters())->toBe(array_map(fn (string $order): string => ltrim($order, '-'), $orders));
    })->with([
        'ascending' => [['rank'], ['rank', 'id']],
        'descending' => [['-rank'], ['-rank', '-id']],
        'descending, then ascending' => [['-rank', 'name'], ['-rank', 'name', 'id']],
        'ascending, then descending' => [['rank', '-name'], ['rank', '-name', '-id']],
    ]);

    it('ends the order at the key the client sorts by', function (): void {
        $sort = Sort::fromArray(['-id', 'rank'], ['rank', 'id'], 'id');

        expect(sortOrders($sort))->toBe(['-id'])
            ->and($sort->cursorParameters())->toBe(['id']);
    });

    it('ends the order at a unique field, without the key', function (array $fields, array $orders): void {
        $sort = Sort::fromArray($fields, ['rank', 'email'], 'id', unique: ['email']);

        expect(sortOrders($sort))->toBe($orders)
            ->and($sort->fields())->toHaveCount(count($fields));
    })->with([
        'alone' => [['-email'], ['-email']],
        'first' => [['email', '-rank'], ['email']],
        'last' => [['-rank', 'email'], ['-rank', 'email']],
        'not requested' => [['-rank'], ['-rank', '-id']],
    ]);

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
                ['column' => 'id', 'direction' => 'desc'],
            ]);
    });
});

describe('Sort with fields that may be NULL', function (): void {
    it('marks nullable columns and relation fields, and lists their cursor parameters', function (): void {
        $sort = Sort::fromArray(
            ['-owner_name', 'score', 'rank'],
            ['rank', 'score', 'owner_name'],
            'id',
            nullable: ['score'],
            relations: ['owner_name' => 'owner.name'],
        );

        expect(array_map(fn (SortField $field): array => [$field->toString(), $field->isNullable(), $field->relation()], $sort->orders()))
            ->toBe([
                ['-owner_name', true, 'owner.name'],
                ['score', true, null],
                ['rank', false, null],
                ['id', false, null],
            ])
            ->and($sort->cursorParameters())->toBe(['owner_name', 'score', 'rank', 'id'])
            ->and($sort->nullableCursorParameters())->toBe(['owner_name', 'score']);
    });

    it('ends the order only at a unique field that never holds NULL', function (): void {
        $sort = Sort::fromArray(['email', 'code'], ['email', 'code'], 'id', unique: ['email', 'code'], nullable: ['email']);

        expect(sortOrders($sort))->toBe(['email', 'code']);
    });

    it('never ends the order at a relation field declared unique', function (): void {
        $sort = Sort::fromArray(['owner_name'], ['owner_name'], 'id', unique: ['owner_name'], relations: ['owner_name' => 'owner.name']);

        expect(sortOrders($sort))->toBe(['owner_name', 'id']);
    });

    it('rejects a relation field that is not a relation and a column of it, requested or not', function (string $path): void {
        expect(fn () => Sort::fromArray(['rank'], ['rank', 'x'], 'id', relations: ['x' => $path]))
            ->toThrow(RelationNotSortable::class, "Sort field \"x\": \"{$path}\" must be a relation of the list model and a column of it, as in \"profile.personnel_number\".");
    })->with([
        'no column' => ['owner'],
        'a nested relation' => ['owner.team.name'],
        'no relation' => ['.name'],
        'an empty column' => ['owner.'],
    ]);

    it('applies only an order whose fields never hold NULL', function (): void {
        $query = SortTestItemModel::query();

        Sort::fromArray(['-rank'], ['rank', 'score'], 'id', nullable: ['score'])->apply($query);

        expect(fn () => Sort::fromArray(['score'], ['score'], 'id', nullable: ['score'])->apply($query))
            ->toThrow(SortNotApplicable::class, 'The sort by "score" may be NULL: page it with Sort::paginate(), not with apply() and cursorPaginate().');
    });
});

describe('SortField', function (): void {
    it('prints itself as a client requests it', function (): void {
        expect((new SortField('rank'))->toString())->toBe('rank')
            ->and((new SortField('rank', SortDirection::Descending))->toString())->toBe('-rank');
    });

    it('is nullable as a nullable column or a relation field', function (): void {
        expect((new SortField('rank'))->isNullable())->toBeFalse()
            ->and((new SortField('score', nullable: true))->isNullable())->toBeTrue()
            ->and((new SortField('owner_name', relation: 'owner.name'))->isNullable())->toBeTrue();
    });

    it('reverses its direction and keeps the rest', function (): void {
        $reversed = (new SortField('owner_name', SortDirection::Descending, relation: 'owner.name'))->reversed();

        expect([$reversed->toString(), $reversed->isNullable(), $reversed->relation()])->toBe(['owner_name', true, 'owner.name'])
            ->and((new SortField('rank'))->reversed()->toString())->toBe('-rank');
    });
});
