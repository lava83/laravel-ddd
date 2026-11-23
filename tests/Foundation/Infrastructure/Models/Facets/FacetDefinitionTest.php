<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\FacetDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\CustomFilterNotValid;

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

    it('names no filter type by default', function (): void {
        expect(FacetDefinition::column('status', 'status_id')->filterType())->toBeNull()
            ->and(FacetDefinition::relation('services', 'services', 'id')->filterType())->toBeNull();
    });

    it('takes a custom filter type for a column and a relation facet', function (): void {
        expect(FacetDefinition::column('linked', 'user_id', filterType: '$eqLinkedUsers')->filterType())->toBe('$eqLinkedUsers')
            ->and(FacetDefinition::relation('services', 'services', 'id', filterType: '$hasService')->filterType())->toBe('$hasService');
    });

    it('rejects a built-in operator as filter type', function (): void {
        expect(fn () => FacetDefinition::column('status', 'status_id', filterType: '$eq'))
            ->toThrow(CustomFilterNotValid::class, '"$eq"')
            ->and(fn () => FacetDefinition::expression('linked', DB::raw('1'), 'linked', '$in'))
            ->toThrow(CustomFilterNotValid::class, '"$in"');
    });

    it('defines an expression facet with its filter target and no bindings by default', function (): void {
        $expression = DB::raw("CASE WHEN a IS NULL THEN 'none' ELSE 'some' END");
        $facet = FacetDefinition::expression('linked', $expression, 'linked_users');

        expect($facet->key())->toBe('linked')
            ->and($facet->rawExpression())->toBe($expression)
            ->and($facet->bindings())->toBe([])
            ->and($facet->relationName())->toBeNull()
            ->and($facet->filterTarget())->toBe('linked_users')
            ->and($facet->filterType())->toBeNull();
    });

    it('takes the bindings and the filter type of an expression facet', function (): void {
        $facet = FacetDefinition::expression('score', DB::raw('score > ?'), 'score', '$eqScore', [0]);

        expect($facet->bindings())->toBe([0])
            ->and($facet->filterType())->toBe('$eqScore');
    });

    it('has no column for an expression facet', function (): void {
        expect(fn () => FacetDefinition::expression('linked', DB::raw('1'), 'linked')->columnName())
            ->toThrow(LogicException::class, 'The facet "linked" counts an expression and has no column.');
    });

    it('has no expression for a column or a relation facet', function (): void {
        expect(FacetDefinition::column('status', 'status_id')->rawExpression())->toBeNull()
            ->and(FacetDefinition::relation('services', 'services', 'id')->rawExpression())->toBeNull()
            ->and(FacetDefinition::column('status', 'status_id')->bindings())->toBe([]);
    });
});
