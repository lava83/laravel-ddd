<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetBucketReadModel;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;

describe('FacetReadModel', function (): void {
    it('exposes its key', function (): void {
        expect((new FacetReadModel('status'))->key())->toBe('status');
    });

    it('serialises to a list of value and count pairs', function (): void {
        $facet = new FacetReadModel('status', [
            new FacetBucketReadModel(1, 120),
            new FacetBucketReadModel(2, 7),
        ]);

        expect($facet->toArray())->toBe([
            ['value' => 1, 'count' => 120],
            ['value' => 2, 'count' => 7],
        ]);
    });

    it('orders the buckets by value ascending with the null bucket first', function (): void {
        $facet = new FacetReadModel('status', [
            new FacetBucketReadModel(2, 7),
            new FacetBucketReadModel(null, 3),
            new FacetBucketReadModel(1, 120),
        ]);

        expect($facet->toArray())->toBe([
            ['value' => null, 'count' => 3],
            ['value' => 1, 'count' => 120],
            ['value' => 2, 'count' => 7],
        ])->and($facet->buckets()->keys()->all())->toBe([0, 1, 2]);
    });

    it('orders string values ascending', function (): void {
        $facet = new FacetReadModel('kind', [
            new FacetBucketReadModel('pending', 1),
            new FacetBucketReadModel('active', 4),
            new FacetBucketReadModel(null, 2),
        ]);

        expect($facet->buckets()->map(fn (FacetBucketReadModel $bucket) => $bucket->value())->all())
            ->toBe([null, 'active', 'pending']);
    });

    it('accepts the buckets from any iterable', function (): void {
        $generator = (function (): Generator {
            yield new FacetBucketReadModel(2, 1);
            yield new FacetBucketReadModel(1, 1);
        })();

        expect((new FacetReadModel('a', new Collection([new FacetBucketReadModel(1, 1)])))->buckets())->toHaveCount(1)
            ->and((new FacetReadModel('b', $generator))->toArray())->toBe([
                ['value' => 1, 'count' => 1],
                ['value' => 2, 'count' => 1],
            ]);
    });

    it('hands out a copy of its buckets', function (): void {
        $facet = new FacetReadModel('status', [new FacetBucketReadModel(1, 120)]);

        $facet->buckets()->push(new FacetBucketReadModel(2, 7));

        expect($facet->buckets())->toHaveCount(1);
    });

    it('has no buckets when nothing was counted', function (): void {
        $facet = new FacetReadModel('status');

        expect($facet->buckets())->toBeEmpty()
            ->and($facet->toArray())->toBe([]);
    });
});
