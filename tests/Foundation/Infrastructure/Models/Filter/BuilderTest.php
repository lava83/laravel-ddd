<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Tests\TestCase;

it('initializes the builder', function () {
    $builder = new Builder();

    expect($builder)->toBeInstanceOf(Builder::class);
});

it('has no initial filters', function () {
    $builder = new Builder();

    expect($builder)->toHaveCount(0);
});

describe('Builder equal', function () {
    it('can build an builder with equal filter', function () {
        $builder = new Builder();

        $builder->eq('foo', 'bar');

        expect($builder)->toHaveCount(1)
            ->and($builder->toArray())->toBeArray()
            ->and($builder->toArray())->toMatchArray([
                [
                    'type' => '$eq',
                    'target' => 'foo',
                    'value' => 'bar',
                ]
            ]);
    });
});
