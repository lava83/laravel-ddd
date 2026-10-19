<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Eloquent\Filters\EloquentQueryFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\EloquentFilterTestFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\EloquentFilterTestModel;

describe('apply', function (): void {
    it('runs every callable of the filter against the query', function (): void {
        $query = (new EloquentFilterTestFilter('status', 'active'))->apply(EloquentFilterTestModel::query());

        expect($query->toSql())->toBe('select * from "eloquent_filter_items" where "status" = ? order by "status" asc')
            ->and($query->getBindings())->toBe(['active']);
    });

    it('returns the query it was given, extended', function (): void {
        $query = EloquentFilterTestModel::query();

        expect((new EloquentFilterTestFilter('status', 'active'))->apply($query))->toBeInstanceOf($query::class);
    });

    it('leaves the query alone when the filter has no callables', function (): void {
        $filter = new class extends EloquentQueryFilter
        {
            public static function fromArray(array $data): static
            {
                return new self;
            }

            protected function filters(): array
            {
                return [];
            }
        };

        $query = EloquentFilterTestModel::query();

        expect($filter->apply($query)->toSql())->toBe('select * from "eloquent_filter_items"');
    });

    it('applies the callables in the order they are listed', function (): void {
        $filter = new class extends EloquentQueryFilter
        {
            public static function fromArray(array $data): static
            {
                return new self;
            }

            protected function filters(): array
            {
                return [
                    'first' => fn ($query) => $query->orderBy('a'),
                    'second' => fn ($query) => $query->orderBy('b'),
                ];
            }
        };

        expect($filter->apply(EloquentFilterTestModel::query())->toSql())
            ->toBe('select * from "eloquent_filter_items" order by "a" asc, "b" asc');
    });
});

describe('a callable that returns another builder', function (): void {
    it('hands the returned builder to the next callable and back to the caller', function (): void {
        $filter = new class extends EloquentQueryFilter
        {
            public static function fromArray(array $data): static
            {
                return new self;
            }

            protected function filters(): array
            {
                return [
                    'copy' => fn ($query) => $query->clone()->where('copied', 1),
                    'next' => fn ($query) => $query->where('next', 2),
                ];
            }
        };

        $original = EloquentFilterTestModel::query();
        $result = $filter->apply($original);

        expect($result->toSql())->toBe('select * from "eloquent_filter_items" where "copied" = ? and "next" = ?')
            ->and($result->getBindings())->toBe([1, 2])
            ->and($original->toSql())->toBe('select * from "eloquent_filter_items"');
    });
});

describe('fromArray', function (): void {
    it('builds the filter from its array form', function (): void {
        $filter = EloquentFilterTestFilter::fromArray(['type' => '$eq', 'target' => 'status', 'value' => 'active']);

        expect($filter)->toBeInstanceOf(EloquentFilterTestFilter::class)
            ->and($filter->apply(EloquentFilterTestModel::query())->getBindings())->toBe(['active']);
    });
});

describe('filtering scope', function (): void {
    it('applies the filter through the model', function (): void {
        $query = EloquentFilterTestModel::query()->filtering(new EloquentFilterTestFilter('status', 'active'));

        expect($query->toSql())->toBe('select * from "eloquent_filter_items" where "status" = ? order by "status" asc')
            ->and($query->getBindings())->toBe(['active']);
    });

    it('can be chained with other constraints', function (): void {
        $query = EloquentFilterTestModel::query()
            ->where('owner_id', 7)
            ->filtering(new EloquentFilterTestFilter('status', 'active'));

        expect($query->toSql())->toBe('select * from "eloquent_filter_items" where "owner_id" = ? and "status" = ? order by "status" asc')
            ->and($query->getBindings())->toBe([7, 'active']);
    });
});
