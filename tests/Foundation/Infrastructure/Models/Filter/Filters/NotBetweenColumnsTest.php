<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetweenColumns;

describe(
    'Initialize not between columns filter',
    function (): void {
        it('creates a not between columns filter', function () {
            $equal = new NotBetweenColumns('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(NotBetweenColumns::class);
        });

        it('has the correct value', function() {
           expect((new NotBetweenColumns('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new NotBetweenColumns('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new NotBetweenColumns('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$notBetweenColumns',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('validate of having array with not only one value', function() {
            $equalNotAllowedValue = new NotBetweenColumns('foo', ['bar']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('validate of having array with more than two values', function() {
            $equalNotAllowedValue = new NotBetweenColumns('foo', ['bar', 'baz', 'qux']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('validate of having required values', function() {
            $equalNotAllowedValue = new NotBetweenColumns('foo', ['', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('validate of having both values are required', function() {
            $equalNotAllowedValue = new NotBetweenColumns('foo', ['bar', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');

        it('validate of only strings are allowed', function() {
            $equalNotAllowedValue = new NotBetweenColumns('foo', ['bar', 123]);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",123]" is not valid.');
    }
);


