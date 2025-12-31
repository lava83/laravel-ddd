<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Like;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotLike;

describe(
    'Initialize not like filter',
    function (): void {
        it('creates a not like filter', function () {
            $notLike = new NotLike('foo', 'bar');

            expect($notLike)->toBeInstanceOf(NotLike::class);
        });

        it('has the correct value', function() {
           expect((new NotLike('foo', 'bar'))->value())->toBe('bar');
        });

        it('has the correct target', function() {
            expect((new NotLike('foo', 'bar'))->target())->toBe('foo');
        });

        it('has the correct array', function() {
            expect((new NotLike('foo', 'bar'))->toArray())->toBe([
                'type' => '$notLike',
                'target' => 'foo',
                'value' => 'bar',
            ]);
        });

        it('throws exception if the filter value is empty', function() {
            (new NotLike('foo', ''))->toArray();
        })->throws(FilterValueNotValid::class, 'The filter value "" is not valid.');
    }
);


