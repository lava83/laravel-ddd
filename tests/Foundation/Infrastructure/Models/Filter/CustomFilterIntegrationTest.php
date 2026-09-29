<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IndexZer0\EloquentFiltering\Filter\Exceptions\DeniedFilterException;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\OwnedByFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\FilterCustomDeniedTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\FilterCustomTestModel;

beforeEach(function (): void {
    config()->set('eloquent-filtering.custom_filters', [OwnedByFilter::class]);
    config()->set('eloquent-filtering.suppress.filter.denied', false);

    Schema::dropIfExists('filter_custom_test_items');
    Schema::create('filter_custom_test_items', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('owner_id');
        $table->unsignedInteger('version')->default(1);
        $table->timestamps();
    });

    foreach ([['a', 1], ['b', 1], ['c', 2], ['d', 3]] as [$name, $owner]) {
        FilterCustomTestModel::query()->create(['name' => $name, 'owner_id' => $owner]);
    }
});

describe('Builder::custom() with eloquent-filtering', function (): void {
    it('narrows the result set with a scalar value', function () {
        $filter = Builder::make()->custom('$ownedBy', 1);

        $names = FilterCustomTestModel::query()->filter($filter->toArray())->orderBy('name')->pluck('name');

        expect($names->all())->toBe(['a', 'b']);
    });

    it('narrows the result set with an array value', function () {
        $filter = Builder::make()->custom('$ownedBy', [2, 3]);

        $names = FilterCustomTestModel::query()->filter($filter->toArray())->orderBy('name')->pluck('name');

        expect($names->all())->toBe(['c', 'd']);
    });

    it('combines with built-in filters as AND', function () {
        $filter = Builder::make()->custom('$ownedBy', 1)->eq('name', 'b');

        $names = FilterCustomTestModel::query()->filter($filter->toArray())->pluck('name');

        expect($names->all())->toBe(['b']);
    });

    it('rejects the type on a model that does not allow it', function () {
        $filter = Builder::make()->custom('$ownedBy', 1);

        FilterCustomDeniedTestModel::query()->filter($filter->toArray())->get();
    })->throws(DeniedFilterException::class);

    it('ignores the filter on a model that does not allow it when denied filters are suppressed', function () {
        config()->set('eloquent-filtering.suppress.filter.denied', true);

        $filter = Builder::make()->custom('$ownedBy', 1);

        expect(FilterCustomDeniedTestModel::query()->filter($filter->toArray())->count())->toBe(4);
    });
});
