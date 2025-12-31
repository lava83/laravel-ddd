<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Like;

describe(
    'Initialize like filter',
    function (): void {
        it('creates an like filter', function () {
            $like = new Like('foo', 'bar');

            expect($like)->toBeInstanceOf(Like::class);
        });

        it('has the correct value', function() {
           expect((new Like('foo', 'bar'))->value())->toBe('bar');
        });

        it('has the correct target', function() {
            expect((new Like('foo', 'bar'))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new Like('foo', 'bar'))->toArray())->toBe([
                'type' => '$like',
                'target' => 'foo',
                'value' => 'bar',
            ]);
        });

        it('throws exception if the filter value is empty', function() {
            (new Like('foo', ''))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);


