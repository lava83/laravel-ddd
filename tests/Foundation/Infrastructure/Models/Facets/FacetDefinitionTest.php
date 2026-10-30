<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;

describe('FacetDefinition', function (): void {
    it('defines a column facet that targets the column by default', function (): void {
        $facet = FacetDefinition::column('status', 'status_id');

        expect($facet->key())->toBe('status')
            ->and($facet->columnName())->toBe('status_id')
            ->and($facet->relationName())->toBeNull()
            ->and($facet->filterTarget())->toBe('status_id');
    });

    it('takes an explicit filter target for a column facet', function (): void {
        expect(FacetDefinition::column('status', 'status_id', 'status')->filterTarget())->toBe('status');
    });

    it('defines a relation facet that targets the relation by default', function (): void {
        $facet = FacetDefinition::relation('services', 'services', 'id');

        expect($facet->key())->toBe('services')
            ->and($facet->columnName())->toBe('id')
            ->and($facet->relationName())->toBe('services')
            ->and($facet->filterTarget())->toBe('services');
    });

    it('takes an explicit filter target for a relation facet', function (): void {
        expect(FacetDefinition::relation('services', 'services', 'id', 'service_id')->filterTarget())->toBe('service_id');
    });
});
