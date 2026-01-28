<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe(
    'Initialize equal filter',
    function (): void {
        it('creates an equal filter', function () {
            $equal = new Equal('foo', 'bar');

            expect($equal)->toBeInstanceOf(Equal::class);
        });

        it('has the correct value', function () {
            expect((new Equal('foo', 'bar'))->value())->toBe('bar');
        });

        it('has the correct target', function () {
            expect((new Equal('foo', 'bar'))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new Equal('foo', 'bar'))->toArray())->toBe([
                'type' => '$eq',
                'target' => 'foo',
                'value' => 'bar',
            ]);
        });

        it('throws exception if the filter value is empty', function () {
            (new Equal('foo', ''))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);
