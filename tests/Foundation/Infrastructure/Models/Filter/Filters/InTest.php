<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\In;

describe(
    'Initialize in filter',
    function (): void {
        it('creates a in filter', function () {
            $in = new In('foo', ['bar', 'baz']);

            expect($in)->toBeInstanceOf(In::class);
        });

        it('has the correct value', function () {
            expect((new In('foo', ['bar', 'baz']))->value())->toBe(['bar', 'baz']);
        });

        it('has the correct target', function () {
            expect((new In('foo', ['bar', 'baz']))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new In('foo', ['bar', 'baz']))->toArray())->toBe([
                'type' => '$in',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ]);
        });

        it('throws exception when value array is empty', function () {
            (new In('foo', []))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "[]" is not valid.');

        it('throws exception when values are empty', function () {
            (new In('foo', ['', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["",""]" is not valid.');

        it('throws exception when one value is empty', function () {
            (new In('foo', ['bar', '']))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "["bar",""]" is not valid.');
    }
);
