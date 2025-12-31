<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThanOrEqualTo;

describe(
    'Initialize lte filter',
    function (): void {
        it('creates an lte filter', function () {
            $lt = new LessThanOrEqualTo('foo', 123);

            expect($lt)->toBeInstanceOf(LessThanOrEqualTo::class);
        });

        it('has the correct number value', function() {
           expect((new LessThanOrEqualTo('foo', 123))->value())
               ->toBe(123)
               ->toBeInt()
               ->and((new LessThanOrEqualTo('foo', 123.123))->value())
               ->toBe(123.123)
               ->toBeFloat()
               ->and((int)(new LessThanOrEqualTo('foo', '123'))->value())
               ->toBe(123)
               ->toBeInt();
        });

        it('has the correct target', function() {
            expect((new LessThanOrEqualTo('foo', 1234))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new LessThanOrEqualTo('foo', 123))->toArray())->toBe([
                'type' => '$lte',
                'target' => 'foo',
                'value' => 123,
            ]);
        });
    }
);


