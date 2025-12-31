<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\In;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotIn;

describe(
    'Initialize not in filter',
    function (): void {
        it('creates a not in filter', function () {
            $equal = new NotIn('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(NotIn::class);
        });

        it('has the correct value', function() {
           expect((new NotIn('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new NotIn('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new NotIn('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$notIn',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when array has only one value', function() {
            $equalNotAllowedValue = new NotIn('foo', ['bar']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception when array has more than two values', function() {
            $equalNotAllowedValue = new NotIn('foo', ['bar', 'baz', 'qux']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception when both values are empty', function() {
            $equalNotAllowedValue = new NotIn('foo', ['', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function() {
            $equalNotAllowedValue = new NotIn('foo', ['bar', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);


