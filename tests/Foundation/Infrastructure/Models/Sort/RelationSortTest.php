<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidCursor;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotApplicable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestAccountModel;

const SORT_TEST_RELATION_FIELDS = [
    'customer_email' => 'customerUser.email',
    'personnel_number' => 'profile.personnel_number',
    'manager_name' => 'manager.name',
    'active_email' => 'activeEmailIdentifier.value',
    'latest_identifier' => 'latestIdentifier.value',
    'parent_number' => 'parent.number',
    'status_id' => 'manager.status_id',
];

/**
 * @param  list<string>  $fields
 */
function relationSort(array $fields): Sort
{
    return Sort::fromArray($fields, ['number', ...array_keys(SORT_TEST_RELATION_FIELDS)], 'id', relations: SORT_TEST_RELATION_FIELDS);
}

/**
 * @param  (Closure(EloquentBuilder<SortTestAccountModel>): mixed)|null  $scope
 * @return ReadModelCursorPaginator<SortTestAccountModel, stdClass>
 */
function relationSortPage(Sort $sort, int $perPage, ?string $cursor = null, ?Closure $scope = null): ReadModelCursorPaginator
{
    $query = SortTestAccountModel::query();

    if ($scope !== null) {
        $scope($query);
    }

    return ReadModelCursorPaginator::fromPage(
        $sort->paginate($query, $perPage, cursor: $cursor),
        fn (SortTestAccountModel $model): stdClass => (object) ['id' => $model->id],
    );
}

/**
 * Walk all pages forward, then back from the last one, and return the ids of
 * both walks in list order. Every cursor must pass the cursor rule.
 *
 * @return array{list<int>, list<int>}
 */
function relationSortWalk(Sort $sort, int $perPage): array
{
    $ids = fn (ReadModelCursorPaginator $page): array => array_map(fn (stdClass $item): int => $item->id, $page->items());
    $rule = fn (?Cursor $cursor): bool => $cursor === null
        || validator(['cursor' => $cursor->encode()], ['cursor' => [new ValidCursor($sort->cursorParameters(), $sort->nullableCursorParameters())]])->passes();

    $page = relationSortPage($sort, $perPage);
    $forward = [$ids($page)];

    while ($page->nextCursor() !== null && count($forward) < 20) {
        expect($rule($page->nextCursor()))->toBeTrue();
        $page = relationSortPage($sort, $perPage, $page->nextCursor()->encode());
        $forward[] = $ids($page);
    }

    $backward = [$ids($page)];

    while ($page->previousCursor() !== null && count($backward) < 20) {
        expect($rule($page->previousCursor()))->toBeTrue();
        $page = relationSortPage($sort, $perPage, $page->previousCursor()->encode());
        $backward[] = $ids($page);
    }

    return [array_merge(...$forward), array_merge(...array_reverse($backward))];
}

/*
 * The accounts on the default connection, their customer users and profiles
 * in a database attached to it, which the connection sort_customers reports
 * as its name.
 *
 * id number customer user    profile                      manager       identifiers (driver value active)  parent
 * 1  A-1    carl             P-300                        Ben           phone 0001, email x@1               -
 * 2  A-2    -                P-100                        Mia           email a@2, email b@2                A-1
 * 3  A-3    anna             P-200 (soft-deleted)         -             email c@3 inactive                  A-1
 * 4  A-4    bert (deleted)   -                            Zoe (deleted) -                                   A-2
 * 5  A-5    unknown id 9     P-050, P-400                 Ben           email d@5                           -
 * 6  A-6    anna             -                            Mia           phone 0006                          A-3
 *
 * The users and profiles tables also exist in the main database, every row
 * "decoy" and linked to every account. SQLite looks for a table without a
 * database name in main first, so a table the sort fails to qualify reads
 * the decoys.
 */
beforeEach(function (): void {
    config()->set('database.connections.sort_customers', ['driver' => 'sqlite', 'database' => 'sort_customers', 'prefix' => '']);
    DB::statement("attach database ':memory:' as sort_customers");

    foreach (['sort_test_accounts', 'sort_test_managers', 'sort_test_identifiers'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('sort_test_accounts', function (Blueprint $table): void {
        $table->id();
        $table->string('number');
        $table->unsignedInteger('customer_user_id')->nullable();
        $table->unsignedInteger('manager_id')->nullable();
        $table->unsignedInteger('parent_id')->nullable();
        $table->unsignedInteger('status_id');
    });
    Schema::create('sort_test_managers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('status_id');
        $table->softDeletes();
    });
    Schema::create('sort_test_identifiers', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('account_id');
        $table->string('driver');
        $table->string('value');
        $table->boolean('active');
    });

    foreach (['main', 'sort_customers'] as $database) {
        Schema::create("{$database}.users", function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->softDeletes();
        });
        Schema::create("{$database}.profiles", function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->string('personnel_number');
            $table->softDeletes();
        });
    }

    $deleted = '2026-01-01 00:00:00';

    DB::table('sort_test_accounts')->insert([
        ['id' => 1, 'number' => 'A-1', 'customer_user_id' => 1, 'manager_id' => 2, 'parent_id' => null, 'status_id' => 1],
        ['id' => 2, 'number' => 'A-2', 'customer_user_id' => null, 'manager_id' => 1, 'parent_id' => 1, 'status_id' => 2],
        ['id' => 3, 'number' => 'A-3', 'customer_user_id' => 2, 'manager_id' => null, 'parent_id' => 1, 'status_id' => 1],
        ['id' => 4, 'number' => 'A-4', 'customer_user_id' => 3, 'manager_id' => 3, 'parent_id' => 2, 'status_id' => 2],
        ['id' => 5, 'number' => 'A-5', 'customer_user_id' => 9, 'manager_id' => 2, 'parent_id' => null, 'status_id' => 1],
        ['id' => 6, 'number' => 'A-6', 'customer_user_id' => 2, 'manager_id' => 1, 'parent_id' => 3, 'status_id' => 1],
    ]);
    DB::table('sort_test_managers')->insert([
        ['id' => 1, 'name' => 'Mia', 'status_id' => 2, 'deleted_at' => null],
        ['id' => 2, 'name' => 'Ben', 'status_id' => 1, 'deleted_at' => null],
        ['id' => 3, 'name' => 'Zoe', 'status_id' => 1, 'deleted_at' => $deleted],
    ]);
    DB::table('sort_test_identifiers')->insert([
        ['account_id' => 1, 'driver' => 'phone', 'value' => '0001', 'active' => true],
        ['account_id' => 1, 'driver' => 'email', 'value' => 'x@1', 'active' => true],
        ['account_id' => 2, 'driver' => 'email', 'value' => 'a@2', 'active' => true],
        ['account_id' => 2, 'driver' => 'email', 'value' => 'b@2', 'active' => true],
        ['account_id' => 3, 'driver' => 'email', 'value' => 'c@3', 'active' => false],
        ['account_id' => 5, 'driver' => 'email', 'value' => 'd@5', 'active' => true],
        ['account_id' => 6, 'driver' => 'phone', 'value' => '0006', 'active' => true],
    ]);
    DB::table('sort_customers.users')->insert([
        ['id' => 1, 'email' => 'carl@customer.test', 'deleted_at' => null],
        ['id' => 2, 'email' => 'anna@customer.test', 'deleted_at' => null],
        ['id' => 3, 'email' => 'bert@customer.test', 'deleted_at' => $deleted],
    ]);
    DB::table('sort_customers.profiles')->insert([
        ['account_id' => 1, 'personnel_number' => 'P-300', 'deleted_at' => null],
        ['account_id' => 2, 'personnel_number' => 'P-100', 'deleted_at' => null],
        ['account_id' => 3, 'personnel_number' => 'P-200', 'deleted_at' => $deleted],
        ['account_id' => 5, 'personnel_number' => 'P-400', 'deleted_at' => null],
        ['account_id' => 5, 'personnel_number' => 'P-050', 'deleted_at' => null],
    ]);

    foreach (range(1, 9) as $id) {
        DB::table('main.users')->insert(['id' => $id, 'email' => 'decoy']);
        DB::table('main.profiles')->insert(['account_id' => $id, 'personnel_number' => 'decoy']);
    }
});

afterEach(function (): void {
    DB::statement('detach database sort_customers');
});

describe('Sort by a relation field', function (): void {
    it('pages every row once in both directions, NULL last ascending', function (array $fields, array $order): void {
        foreach ([1, 2, 4, 6] as $perPage) {
            [$forward, $backward] = relationSortWalk(relationSort($fields), $perPage);

            expect($forward)->toBe($order)
                ->and($backward)->toBe($order);
        }
    })->with([
        'a BelongsTo on another connection' => [['customer_email'], [3, 6, 1, 2, 4, 5]],
        'a BelongsTo on another connection, descending' => [['-customer_email'], [5, 4, 2, 1, 6, 3]],
        'a HasOne on another connection, the smallest of several' => [['personnel_number'], [5, 2, 1, 3, 4, 6]],
        'a HasOne on another connection, the smallest of several descending' => [['-personnel_number'], [6, 4, 3, 1, 2, 5]],
        'a BelongsTo on the connection' => [['manager_name'], [1, 5, 2, 6, 3, 4]],
        'a BelongsTo on the connection, descending' => [['-manager_name'], [4, 3, 6, 2, 5, 1]],
        'a constrained HasOne on the connection' => [['active_email'], [2, 5, 1, 3, 4, 6]],
        'a one-of-many HasOne on the connection' => [['latest_identifier'], [6, 2, 3, 5, 1, 4]],
        'a BelongsTo to the list itself' => [['parent_number'], [2, 3, 4, 6, 1, 5]],
        'a relation field, then a column' => [['-manager_name', 'number'], [3, 4, 2, 6, 1, 5]],
        'two relation fields' => [['customer_email', '-manager_name'], [3, 6, 1, 4, 2, 5]],
    ]);

    it('reads a related table of another connection from its database', function (): void {
        DB::enableQueryLog();

        relationSortPage(relationSort(['customer_email', 'personnel_number']), 2);

        $sql = DB::getQueryLog()[0]['query'];

        expect($sql)->toContain('from "sort_customers"."users"')
            ->and($sql)->toContain('from "sort_customers"."profiles"')
            ->and($sql)->not->toContain('from "users"')
            ->and($sql)->not->toContain('from "profiles"');
    });

    it('keeps the columns of the list model and selects the relation field under its alias', function (): void {
        $models = relationSort(['status_id'])->paginate(SortTestAccountModel::query()->with('manager'), 6)->items();

        expect(array_map(fn (SortTestAccountModel $model): array => [$model->id, $model->status_id, $model->laravel_ddd_sort_status_id], $models))
            ->toBe([[1, 1, 1], [5, 1, 1], [2, 2, 2], [6, 1, 2], [3, 1, null], [4, 2, null]])
            ->and($models[0]->relationLoaded('manager'))->toBeTrue()
            ->and($models[0]->manager?->name)->toBe('Ben');
    });

    it('takes the cursor value of a relation field from its alias, not from a column of the same name', function (): void {
        // The fourth row is account 6 with status 1, its manager Mia has status 2; the fifth is account 3 with status 1 and no manager.
        expect(relationSortPage(relationSort(['status_id']), 4)->nextCursor()?->parameter('status_id'))->toBe(2)
            ->and(relationSortPage(relationSort(['status_id']), 5)->nextCursor()?->parameter('status_id'))->toBeNull();
    });

    it('selects the value alone when a global scope of the related model selects more columns', function (): void {
        $sort = Sort::fromArray(['x'], ['x'], 'id', relations: ['x' => 'scopedManager.name']);
        $models = $sort->paginate(SortTestAccountModel::query(), 6)->items();

        // Not soft-deleting, so account 4 has Zoe.
        expect(array_map(fn (SortTestAccountModel $model): int => $model->id, $models))->toBe([1, 5, 2, 6, 4, 3]);
    });

    it('compares a selected alias by its expression in the cursor constraint, as Laravel does', function (): void {
        $sort = Sort::fromArray(['-identifiers_count'], ['identifiers_count'], 'id');
        $query = fn (): EloquentBuilder => SortTestAccountModel::query()->withCount('identifiers');
        $ids = fn (CursorPaginator $page): array => array_map(fn (SortTestAccountModel $model): int => $model->id, $page->items());

        $first = $sort->paginate($query(), 2);

        DB::enableQueryLog();
        $second = $sort->paginate($query(), 2, cursor: $first->nextCursor());
        $sql = DB::getQueryLog()[0]['query'];

        expect($ids($first))->toBe([2, 1])
            ->and($ids($second))->toBe([6, 5])
            ->and($ids($sort->apply($query())->cursorPaginate(2, cursor: $first->nextCursor())))->toBe([6, 5])
            // MySQL, MariaDB and PostgreSQL take no alias in WHERE. SQLite does, so only the SQL shows the difference.
            ->and($sql)->toContain('where ((select count(*) from "sort_test_identifiers" where "sort_test_accounts"."id" = "sort_test_identifiers"."account_id") < ?')
            ->and($sql)->not->toContain('"identifiers_count" <');
    });

    it('compares a renamed column by its column in the cursor constraint', function (): void {
        $sort = Sort::fromArray(['label'], ['label'], 'id');

        DB::enableQueryLog();
        $page = $sort->paginate(
            SortTestAccountModel::query()->select('sort_test_accounts.*', 'number as label'),
            2,
            cursor: new Cursor(['label' => 'A-2', 'id' => 2]),
        );

        expect(array_map(fn (SortTestAccountModel $model): int => $model->id, $page->items()))->toBe([3, 4])
            ->and(DB::getQueryLog()[0]['query'])->toContain('("number" > ?')
            ->and(DB::getQueryLog()[0]['query'])->not->toContain('"label" >');
    });

    it('carries NULL in the cursor of a row without a related row', function (): void {
        $page = relationSortPage(relationSort(['customer_email']), 4);

        expect($page->nextCursor()?->toArray())->toBe(['customer_email' => null, 'id' => 2, '_pointsToNextItems' => true]);
    });

    it('works with a whereHas on the same relation, unqualified columns and eager loads', function (): void {
        $scope = fn (EloquentBuilder $query) => $query
            ->with('manager')
            ->where('status_id', 1)
            ->whereHas('manager', fn (EloquentBuilder $manager) => $manager->where('status_id', 1));

        $models = relationSort(['-manager_name'])->paginate(SortTestAccountModel::query()->tap($scope), 10)->items();

        expect(array_map(fn (SortTestAccountModel $model): array => [$model->id, $model->manager?->name], $models))
            ->toBe([[5, 'Ben'], [1, 'Ben']]);
    });

    it('counts the same facets with and without a relation sort', function (): void {
        $count = fn (): array => FacetCounter::count(
            fn (Builder $filter) => SortTestAccountModel::query(),
            Builder::make(),
            Builder::make(),
            FacetDefinition::column('status', 'status_id'),
        )->map(fn (FacetReadModel $facet): array => $facet->toArray())->all();

        $before = $count();
        relationSortPage(relationSort(['customer_email']), 2);

        expect($count())->toBe($before)
            ->and($before)->toBe([[['value' => 1, 'count' => 4], ['value' => 2, 'count' => 2]]]);
    });

    it('rejects a cursor of another relation field', function (): void {
        $cursor = relationSortPage(relationSort(['customer_email']), 2)->nextCursor()?->encode();
        $sort = relationSort(['manager_name']);

        expect(validator(['cursor' => $cursor], ['cursor' => [new ValidCursor($sort->cursorParameters(), $sort->nullableCursorParameters())]])->passes())->toBeFalse();
    });

    it('cannot be applied for cursorPaginate()', function (): void {
        relationSort(['manager_name'])->apply(SortTestAccountModel::query());
    })->throws(SortNotApplicable::class, 'The sort by "manager_name" may be NULL: page it with Sort::paginate()');

    it('rejects a relation that is not to-one', function (string $relation, string $type): void {
        $sort = Sort::fromArray(['x'], ['x'], 'id', relations: ['x' => $relation.'.id']);

        expect(fn () => $sort->paginate(SortTestAccountModel::query(), 2))
            ->toThrow(RelationNotSortable::class, "Sort field \"x\": relation \"{$relation}\" of ".SortTestAccountModel::class." is a {$type}. Only BelongsTo and HasOne can be sorted by.");
    })->with([
        'a HasMany' => ['identifiers', HasMany::class],
        'a BelongsToMany' => ['deputies', BelongsToMany::class],
        'a MorphTo' => ['subject', MorphTo::class],
    ]);

    it('rejects a one-of-many HasOne on another connection', function (): void {
        $sort = Sort::fromArray(['x'], ['x'], 'id', relations: ['x' => 'latestProfile.personnel_number']);

        $sort->paginate(SortTestAccountModel::query(), 2);
    })->throws(RelationNotSortable::class, 'is a one-of-many HasOne on connection "sort_customers", the list on "testing"');

    it('rejects a relation the list model does not have', function (): void {
        $sort = Sort::fromArray(['x'], ['x'], 'id', relations: ['x' => 'unknown.id']);

        $sort->paginate(SortTestAccountModel::query(), 2);
    })->throws(RelationNotSortable::class, 'Sort field "x": "unknown" is not a relation of '.SortTestAccountModel::class.'.');
});
