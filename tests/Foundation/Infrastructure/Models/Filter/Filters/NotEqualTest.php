<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotEqual;

describe(
    'Initialize not equal filter',
    function (): void {
        it('creates a not equal filter', function () {
            $equal = new NotEqual('foo', 'bar');

            expect($equal)->toBeInstanceOf(NotEqual::class);
        });

        it('has the correct value', function() {
           expect((new NotEqual('foo', 'bar'))->value())->toBe('bar');
        });

        it('has the correct target', function() {
            expect((new NotEqual('foo', 'bar'))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new NotEqual('foo', 'bar'))->toArray())->toBe([
                'type' => '$notEq',
                'target' => 'foo',
                'value' => 'bar',
            ]);
        });

        it('validate of having string or integer or float value', function() {
            $equalNotAllowedValue = new NotEqual('foo', '');
            $equalNotAllowedValue->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);


