<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetweenColumns;

describe(
    'Initialize not between columns filter',
    function (): void {
        it('creates a not between columns filter', function () {
            $notBetweenColumns = new NotBetweenColumns('foo', ['bar', 'baz']);

            expect($notBetweenColumns)->toBeInstanceOf(NotBetweenColumns::class);
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

        it('throws exception if value array has only one value', function() {
            (new NotBetweenColumns('foo', ['bar']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception if value array has more than two values', function() {
            (new NotBetweenColumns('foo', ['bar', 'baz', 'qux']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception if both values are empty', function() {
            (new NotBetweenColumns('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception if one of the two values are empty', function() {
            (new NotBetweenColumns('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');

        it('throws exception if the values are not from type string', function() {
            (new NotBetweenColumns('foo', ['bar', 123]))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",123]" is not valid.');
    }
);


