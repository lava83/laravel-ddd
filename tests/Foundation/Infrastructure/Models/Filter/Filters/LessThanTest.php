<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThan;

describe(
    'Initialize lt filter',
    function (): void {
        it('creates an lt filter', function () {
            $lt = new LessThan('foo', 123);

            expect($lt)->toBeInstanceOf(LessThan::class);
        });

        it('has the correct number value', function() {
           expect((new LessThan('foo', 123))->value())
               ->toBe(123)
               ->toBeInt()
               ->and((new LessThan('foo', 123.123))->value())
               ->toBe(123.123)
               ->toBeFloat();
        });

        it('has the correct target', function() {
            expect((new LessThan('foo', 1234))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new LessThan('foo', 123))->toArray())->toBe([
                'type' => '$lt',
                'target' => 'foo',
                'value' => 123,
            ]);
        });
    }
);


