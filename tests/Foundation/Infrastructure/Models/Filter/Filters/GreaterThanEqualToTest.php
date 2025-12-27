<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThanEqualTo;

describe(
    'Initialize gte filter',
    function (): void {
        it('creates an gte filter', function () {
            $equal = new GreaterThanEqualTo('foo', 123);

            expect($equal)->toBeInstanceOf(GreaterThanEqualTo::class);
        });

        it('has the correct number value', function() {
           expect((new GreaterThanEqualTo('foo', 123))->value())
               ->toBe(123)
               ->toBeInt()
               ->and((new GreaterThanEqualTo('foo', 123.123))->value())
               ->toBe(123.123)
               ->toBeFloat();
        });

        it('has the correct target', function() {
            expect((new GreaterThanEqualTo('foo', 1234))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new GreaterThanEqualTo('foo', 123))->toArray())->toBe([
                'type' => '$gte',
                'target' => 'foo',
                'value' => 123,
            ]);
        });
    }
);


