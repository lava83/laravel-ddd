<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\Exceptions\FacetNotCountable;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetCounter;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\FacetTestNoteModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\FacetTestServiceModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\FacetTestUserModel;

/**
 * @return EloquentBuilder<FacetTestUserModel>
 */
function facetTestBaseQuery(Builder $filter): EloquentBuilder
{
    return FacetTestUserModel::query()->filter($filter->toArray());
}

/**
 * @param  Collection<int, FacetReadModel>  $facets
 * @return array<string, list<array{value: string|int|float|bool|null, count: int}>>
 */
function facetCounts(Collection $facets): array
{
    return $facets->mapWithKeys(fn (FacetReadModel $facet): array => [$facet->key() => $facet->toArray()])->all();
}

/**
 * user  branch status type  notes              services
 * anna  1      1      1     a, a, b            x, y
 * bert  1      1      2     b                  x
 * carl  1      2      1     c (soft-deleted)   gone (soft-deleted)
 * dora  1      null   1
 * erik  2      2      1     a
 * fred  1      1      1     a                  x       (fred is soft-deleted)
 */
beforeEach(function (): void {
    config()->set('database.connections.facet_test_remote', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    foreach (['facet_test_service_user', 'facet_test_services', 'facet_test_notes', 'facet_test_users'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('facet_test_users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->unsignedInteger('branch_id');
        $table->unsignedInteger('status_id')->nullable();
        $table->unsignedInteger('type_id');
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('facet_test_notes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('user_id');
        $table->string('kind');
        $table->softDeletes();
    });
    Schema::create('facet_test_services', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->softDeletes();
    });
    Schema::create('facet_test_service_user', function (Blueprint $table): void {
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('service_id');
    });

    $users = [];

    foreach ([
        'anna' => [1, 1, 1],
        'bert' => [1, 1, 2],
        'carl' => [1, 2, 1],
        'dora' => [1, null, 1],
        'erik' => [2, 2, 1],
        'fred' => [1, 1, 1],
    ] as $name => [$branch, $status, $type]) {
        $users[$name] = FacetTestUserModel::query()->create([
            'email' => "{$name}@example.com",
            'branch_id' => $branch,
            'status_id' => $status,
            'type_id' => $type,
        ]);
    }

    foreach ([['anna', 'a'], ['anna', 'a'], ['anna', 'b'], ['bert', 'b'], ['erik', 'a'], ['fred', 'a']] as [$name, $kind]) {
        FacetTestNoteModel::query()->create(['user_id' => $users[$name]->getKey(), 'kind' => $kind]);
    }

    FacetTestNoteModel::query()->create(['user_id' => $users['carl']->getKey(), 'kind' => 'c'])->delete();

    $x = FacetTestServiceModel::query()->create(['name' => 'x']);
    $y = FacetTestServiceModel::query()->create(['name' => 'y']);
    $gone = FacetTestServiceModel::query()->create(['name' => 'gone']);
    $gone->delete();

    $users['anna']->services()->attach([$x->getKey(), $y->getKey()]);
    $users['bert']->services()->attach($x->getKey());
    $users['carl']->services()->attach($gone->getKey());
    $users['fred']->services()->attach($x->getKey());

    $users['fred']->delete();
});

describe('FacetCounter column facets', function (): void {
    it('counts the rows per value, with NULL as a bucket of its own', function (): void {
        $facets = FacetCounter::count(facetTestBaseQuery(...), Builder::make(), Builder::make(), FacetDefinition::column('status', 'status_id'));

        expect($facets)->toHaveCount(1)
            ->and($facets->first())->toBeInstanceOf(FacetReadModel::class)
            ->and(facetCounts($facets))->toBe([
                'status' => [
                    ['value' => null, 'count' => 1],
                    ['value' => 1, 'count' => 2],
                    ['value' => 2, 'count' => 2],
                ],
            ]);
    });

    it('returns no bucket for a value without rows', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make()->eq('status_id', 99),
            FacetDefinition::column('type', 'type_id'),
        );

        expect(facetCounts($facets))->toBe(['type' => []]);
    });
});

describe('FacetCounter relation facets', function (): void {
    it('counts each parent once per value of a HasMany relation', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make()->eq('status_id', 1),
            FacetDefinition::relation('kinds', 'notes', 'kind'),
        );

        // anna has two "a" notes and counts once for "a".
        expect(facetCounts($facets))->toBe([
            'kinds' => [
                ['value' => 'a', 'count' => 1],
                ['value' => 'b', 'count' => 2],
            ],
        ]);
    });

    it('counts a BelongsToMany relation through the pivot', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make()->eq('type_id', 1),
            FacetDefinition::relation('services', 'services', 'name'),
        );

        expect(facetCounts($facets))->toBe([
            'services' => [
                ['value' => 'x', 'count' => 1],
                ['value' => 'y', 'count' => 1],
            ],
        ]);
    });

    it('rejects a relation on another connection', function (): void {
        expect(fn () => FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::relation('remote', 'remoteNotes', 'kind'),
        ))->toThrow(FacetNotCountable::class, 'lives on connection "facet_test_remote", the list on "testing"');
    });

    it('rejects a relation that is neither HasMany nor BelongsToMany', function (): void {
        expect(fn () => FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::relation('note', 'note', 'kind'),
        ))->toThrow(FacetNotCountable::class, 'is a Illuminate\Database\Eloquent\Relations\HasOne;');
    });

    it('rejects a MorphToMany, which would lose its morph type', function (): void {
        expect(fn () => FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::relation('tagged', 'taggedServices', 'name'),
        ))->toThrow(FacetNotCountable::class, 'is a Illuminate\Database\Eloquent\Relations\MorphToMany;');
    });

    it('rejects an unknown relation', function (): void {
        expect(fn () => FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::relation('unknown', 'unknown', 'name'),
        ))->toThrow(FacetNotCountable::class, 'has no relation "unknown"');
    });
});

describe('FacetCounter filters', function (): void {
    it("drops the facet's own requested filter and keeps every other one", function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make()->eq('status_id', 2)->eq('type_id', 1),
            FacetDefinition::column('status', 'status_id'),
            FacetDefinition::column('type', 'type_id'),
        );

        expect(facetCounts($facets))->toBe([
            // type_id = 1 stays: anna, carl, dora, erik
            'status' => [
                ['value' => null, 'count' => 1],
                ['value' => 1, 'count' => 1],
                ['value' => 2, 'count' => 2],
            ],
            // status_id = 2 stays: carl, erik
            'type' => [
                ['value' => 1, 'count' => 2],
            ],
        ]);
    });

    it('drops every requested filter on the target, whatever the operator', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make()->eq('status_id', 1)->in('status_id', [1, 2]),
            FacetDefinition::column('status', 'status_id'),
        );

        expect(facetCounts($facets)['status'])->toHaveCount(3);
    });

    it('keeps the scope when a requested filter has the same target', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make()->eq('branch_id', 1),
            Builder::make()->eq('branch_id', 2),
            FacetDefinition::column('branch', 'branch_id'),
        );

        // erik (branch 2) is outside the scope and is not counted.
        expect(facetCounts($facets))->toBe([
            'branch' => [
                ['value' => 1, 'count' => 4],
            ],
        ]);
    });

    it('applies the scope to a relation facet', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make()->eq('branch_id', 1),
            Builder::make(),
            FacetDefinition::relation('kinds', 'notes', 'kind'),
        );

        // erik's "a" note is outside the scope.
        expect(facetCounts($facets))->toBe([
            'kinds' => [
                ['value' => 'a', 'count' => 1],
                ['value' => 'b', 'count' => 2],
            ],
        ]);
    });
});

describe('FacetCounter queries', function (): void {
    it('does not count soft-deleted rows of the list or of a relation', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::column('status', 'status_id'),
            FacetDefinition::relation('kinds', 'notes', 'kind'),
            FacetDefinition::relation('services', 'services', 'name'),
        );

        // fred, carl's "c" note and the "gone" service are soft-deleted.
        expect(facetCounts($facets))->toBe([
            'status' => [
                ['value' => null, 'count' => 1],
                ['value' => 1, 'count' => 2],
                ['value' => 2, 'count' => 2],
            ],
            'kinds' => [
                ['value' => 'a', 'count' => 2],
                ['value' => 'b', 'count' => 2],
            ],
            'services' => [
                ['value' => 'x', 'count' => 2],
                ['value' => 'y', 'count' => 1],
            ],
        ]);
    });

    it('drops eager loads, ordering, limit and offset of the base query', function (): void {
        $paged = fn (Builder $filter): EloquentBuilder => facetTestBaseQuery($filter)
            ->with('notes')
            ->orderBy('email')
            ->limit(1)
            ->offset(1);

        DB::enableQueryLog();

        $facets = FacetCounter::count(
            $paged,
            Builder::make(),
            Builder::make(),
            FacetDefinition::column('status', 'status_id'),
            FacetDefinition::relation('kinds', 'notes', 'kind'),
        );

        $queries = collect(DB::getQueryLog())->pluck('query');

        expect(facetCounts($facets))->toBe([
            'status' => [
                ['value' => null, 'count' => 1],
                ['value' => 1, 'count' => 2],
                ['value' => 2, 'count' => 2],
            ],
            'kinds' => [
                ['value' => 'a', 'count' => 2],
                ['value' => 'b', 'count' => 2],
            ],
        ])
            ->and($queries)->toHaveCount(2)
            ->and($queries->implode("\n"))
            ->not->toContain('order by')
            ->not->toContain('limit')
            ->not->toContain('offset');
    });

    it('returns the facets in the order of the definitions', function (): void {
        $facets = FacetCounter::count(
            facetTestBaseQuery(...),
            Builder::make(),
            Builder::make(),
            FacetDefinition::column('type', 'type_id'),
            FacetDefinition::relation('services', 'services', 'name'),
            FacetDefinition::column('status', 'status_id'),
            FacetDefinition::relation('kinds', 'notes', 'kind'),
        );

        expect($facets->map(fn (FacetReadModel $facet): string => $facet->key())->all())
            ->toBe(['type', 'services', 'status', 'kinds']);
    });

    it('returns an empty collection without definitions', function (): void {
        expect(FacetCounter::count(facetTestBaseQuery(...), Builder::make(), Builder::make()))->toBeEmpty();
    });
});
