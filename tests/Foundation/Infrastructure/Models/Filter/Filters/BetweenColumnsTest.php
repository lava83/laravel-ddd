<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\BetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe(
    'Initialize between columns filter',
    function (): void {
        it('creates a between columns filter', function () {
            $betweenColumns = new BetweenColumns('foo', ['bar', 'baz']);

            expect($betweenColumns)->toBeInstanceOf(BetweenColumns::class);
        });

        it('has the correct value', function () {
            expect((new BetweenColumns('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function () {
            expect((new BetweenColumns('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new BetweenColumns('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$betweenColumns',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when array has only one value', function () {
            (new BetweenColumns('foo', ['bar']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception when array has more than two values', function () {
            (new BetweenColumns('foo', ['bar', 'baz', 'qux']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception when both values are empty', function () {
            (new BetweenColumns('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function () {
            (new BetweenColumns('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');

        it('throws exception when non-string values are provided', function () {
            (new BetweenColumns('foo', ['bar', 123]))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",123]" is not valid.');
    }
);
