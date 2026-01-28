<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThan;

describe(
    'Initialize gt filter',
    function (): void {
        it('creates an gt filter', function () {
            $gt = new GreaterThan('foo', 123);

            expect($gt)->toBeInstanceOf(GreaterThan::class);
        });

        it('has the correct number value', function () {
            expect((new GreaterThan('foo', 123))->value())
                ->toBe(123)
                ->toBeInt()
                ->and((new GreaterThan('foo', 123.123))->value())
                ->toBe(123.123)
                ->toBeFloat();
        });

        it('has the correct target', function () {
            expect((new GreaterThan('foo', 1234))->target())->toBe('foo');
        });

        it('has the correct array', function () {
            expect((new GreaterThan('foo', 123))->toArray())->toBe([
                'type' => '$gt',
                'target' => 'foo',
                'value' => 123,
            ]);
        });
    }
);
