<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\In;

describe(
    'Initialize in filter',
    function (): void {
        it('creates a in filter', function () {
            $equal = new In('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(In::class);
        });

        it('has the correct value', function() {
           expect((new In('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new In('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new In('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$in',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when array has only one value', function() {
            (new In('foo', ['bar']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception when array has more than two values', function() {
            (new In('foo', ['bar', 'baz', 'qux']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception when both values are empty', function() {
            (new In('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function() {
            (new In('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);


