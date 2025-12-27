<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe(
    'Initialize between filter',
    function (): void {
        it('creates an between filter', function () {
            $equal = new Between('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(Between::class);
        });

        it('has the correct value', function() {
           expect((new Between('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new Between('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new Between('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$between',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('validate of having array with not only one value', function() {
            $equalNotAllowedValue = new Between('foo', ['bar']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('validate of having array with more than two values', function() {
            $equalNotAllowedValue = new Between('foo', ['bar', 'baz', 'qux']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('validate of having required values', function() {
            $equalNotAllowedValue = new Between('foo', ['', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('validate of having both values are required', function() {
            $equalNotAllowedValue = new Between('foo', ['bar', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);


