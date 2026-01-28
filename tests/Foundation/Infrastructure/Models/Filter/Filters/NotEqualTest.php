<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotEqual;

describe(
    'Initialize not equal filter',
    function (): void {
        it('creates a not equal filter', function () {
            $notEqual = new NotEqual('foo', 'bar');

            expect($notEqual)->toBeInstanceOf(NotEqual::class);
        });

        it('has the correct value', function () {
            expect((new NotEqual('foo', 'bar'))->value())->toBe('bar');
        });

        it('has the correct target', function () {
            expect((new NotEqual('foo', 'bar'))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new NotEqual('foo', 'bar'))->toArray())->toBe([
                'type' => '$notEq',
                'target' => 'foo',
                'value' => 'bar',
            ]);
        });

        it('throws exception if value is not of type string or number', function () {
            (new NotEqual('foo', ''))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);
