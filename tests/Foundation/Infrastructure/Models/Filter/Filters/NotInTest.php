<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotIn;

describe(
    'Initialize not in filter',
    function (): void {
        it('creates a not in filter', function () {
            $notIn = new NotIn('foo', ['bar', 'baz']);

            expect($notIn)->toBeInstanceOf(NotIn::class);
        });

        it('has the correct value', function () {
            expect((new NotIn('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function () {
            expect((new NotIn('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new NotIn('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$notIn',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when value array is empty', function () {
            (new NotIn('foo', []))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "[]" is not valid.');

        it('throws exception when values are empty', function () {
            (new NotIn('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function () {
            (new NotIn('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);
