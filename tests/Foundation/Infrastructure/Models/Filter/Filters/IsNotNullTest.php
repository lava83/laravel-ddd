<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNotNull;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNull;

describe(
    'Initialize is not null filter',
    function (): void {
        it('creates an is not null filter', function () {
            $equal = new IsNotNull('foo');

            expect($equal)->toBeInstanceOf(IsNotNull::class);
        });

        it('has the correct value', function() {
           expect((new IsNotNull('foo'))->value())->toBeFalse();
        });

        it('has the correct target', function() {
            expect((new IsNotNull('foo'))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new IsNotNull('foo'))->toArray())->toBe([
                'type' => '$null',
                'target' => 'foo',
                'value' => false,
            ]);
        });

        it('throws exception if the filter value is false', function() {
            (new IsNotNull('foo', true))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "1" is not valid.');
    }
);


