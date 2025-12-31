<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;

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

        expect($builder)->toHaveCount(1);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->eq('foo', 'bar');

        $expectedArray = [
            [
                'type' => '$eq',
                'target' => 'foo',
                'value' => 'bar',
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder not equal', function () {
    it('can build an builder with not equal filter', function () {
        $builder = new Builder();

        $builder->neq('foo', 'bar');

        expect($builder)->toHaveCount(1);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->neq('foo', 'bar');

        $expectedArray = [
            [
                'type' => '$notEq',
                'target' => 'foo',
                'value' => 'bar',
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder in', function () {
    it('can build a builder with in filter', function () {
        $builder = new Builder();

        $builder->in('foo', ['bar', 'baz']);

        expect($builder)->toHaveCount(1);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->in('foo', ['bar', 'baz']);

        $expectedArray = [
            [
                'type' => '$in',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});
