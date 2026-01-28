<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe(
    'Initialize between filter',
    function (): void {
        it('creates a between filter', function () {
            $between = new Between('foo', ['bar', 'baz']);

            expect($between)->toBeInstanceOf(Between::class);
        });

        it('has the correct value', function () {
            expect((new Between('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function () {
            expect((new Between('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new Between('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$between',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when array has only one value', function () {
            (new Between('foo', ['bar']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception when array has more than two values', function () {
            (new Between('foo', ['bar', 'baz', 'qux']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception when both values are empty', function () {
            (new Between('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function () {
            (new Between('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);
