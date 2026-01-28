<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetween;

describe(
    'Initialize not between filter',
    function (): void {
        it('creates a not between filter', function () {
            $notBetween = new NotBetween('foo', ['bar', 'baz']);

            expect($notBetween)->toBeInstanceOf(NotBetween::class);
        });

        it('has the correct value', function () {
            expect((new NotBetween('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function () {
            expect((new NotBetween('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new NotBetween('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$notBetween',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception if value array has only one value', function () {
            (new NotBetween('foo', ['bar']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar"]" is not valid.');

        it('throws exception if value array has more than two values', function () {
            (new NotBetween('foo', ['bar', 'baz', 'qux']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar","baz","qux"]" is not valid.');

        it('throws exception if the both values in array are empty', function () {
            (new NotBetween('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception if one of the values are empty', function () {
            (new NotBetween('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);
