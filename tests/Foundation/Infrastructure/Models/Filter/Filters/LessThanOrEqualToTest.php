<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThanEqualTo;

describe(
    'Initialize lte filter',
    function (): void {
        it('creates an lte filter', function () {
            $lt = new LessThanEqualTo('foo', 123);

            expect($lt)->toBeInstanceOf(LessThanEqualTo::class);
        });

        it('has the correct number value', function() {
           expect((new LessThanEqualTo('foo', 123))->value())
               ->toBe(123)
               ->toBeInt()
               ->and((new LessThanEqualTo('foo', 123.123))->value())
               ->toBe(123.123)
               ->toBeFloat();
        });

        it('has the correct target', function() {
            expect((new LessThanEqualTo('foo', 1234))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new LessThanEqualTo('foo', 123))->toArray())->toBe([
                'type' => '$lte',
                'target' => 'foo',
                'value' => 123,
            ]);
        });
    }
);


