<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetBucketReadModel;

describe('FacetBucketReadModel', function (): void {
    it('exposes its value and count', function (): void {
        $bucket = new FacetBucketReadModel(2, 7);

        expect($bucket->value())->toBe(2)
            ->and($bucket->count())->toBe(7);
    });

    it('serialises to value and count', function (): void {
        expect((new FacetBucketReadModel('active', 120))->toArray())->toBe(['value' => 'active', 'count' => 120]);
    });

    it('keeps a null value as a bucket of its own', function (): void {
        $bucket = new FacetBucketReadModel(null, 3);

        expect($bucket->value())->toBeNull()
            ->and($bucket->toArray())->toBe(['value' => null, 'count' => 3]);
    });
});
