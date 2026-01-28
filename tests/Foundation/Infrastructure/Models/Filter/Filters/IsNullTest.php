<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNull;

describe(
    'Initialize is null filter',
    function (): void {
        it('creates an is null filter', function () {
            $equal = new IsNull('foo');

            expect($equal)->toBeInstanceOf(IsNull::class);
        });

        it('has the correct value', function () {
            expect((new IsNull('foo'))->value())->toBeTrue();
        });

        it('has the correct target', function () {
            expect((new IsNull('foo'))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new IsNull('foo'))->toArray())->toBe([
                'type' => '$null',
                'target' => 'foo',
                'value' => true,
            ]);
        });

        it('throws exception if the filter value is false', function () {
            (new IsNull('foo', false))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);
