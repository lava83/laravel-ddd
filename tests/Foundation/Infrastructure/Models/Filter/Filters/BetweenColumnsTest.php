<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\BetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe(
    'Initialize between columns filter',
    function (): void {
        it('creates an between columns filter', function () {
            $equal = new BetweenColumns('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(BetweenColumns::class);
        });

        it('has the correct value', function() {
           expect((new BetweenColumns('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new BetweenColumns('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new BetweenColumns('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$betweenColumns',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('validate of having array with not only one value', function() {
            $equalNotAllowedValue = new BetweenColumns('foo', ['bar']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('validate of having array with more than two values', function() {
            $equalNotAllowedValue = new BetweenColumns('foo', ['bar', 'baz', 'qux']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('validate of having required values', function() {
            $equalNotAllowedValue = new BetweenColumns('foo', ['', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('validate of having both values are required', function() {
            $equalNotAllowedValue = new BetweenColumns('foo', ['bar', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');

        it('validate of only strings are allowed', function() {
            $equalNotAllowedValue = new BetweenColumns('foo', ['bar', 123]);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",123]" is not valid.');
    }
);


