<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetween;

describe(
    'Initialize not between filter',
    function (): void {
        it('creates an not between filter', function () {
            $equal = new NotBetween('foo', ['bar', 'baz']);

            expect($equal)->toBeInstanceOf(NotBetween::class);
        });

        it('has the correct value', function() {
           expect((new NotBetween('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function() {
            expect((new NotBetween('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new NotBetween('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$notBetween',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('validate of having array with not only one value', function() {
            $equalNotAllowedValue = new NotBetween('foo', ['bar']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('validate of having array with more than two values', function() {
            $equalNotAllowedValue = new NotBetween('foo', ['bar', 'baz', 'qux']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('validate of having required values', function() {
            $equalNotAllowedValue = new NotBetween('foo', ['', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('validate of having both values are required', function() {
            $equalNotAllowedValue = new NotBetween('foo', ['bar', '']);
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);


