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

describe('Builder not in', function () {
    it('can build a builder with not in filter', function () {
        $builder = new Builder();

        $builder->notIn('foo', ['bar', 'baz']);

        expect($builder)->toHaveCount(1);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->notIn('foo', ['bar', 'baz']);

        $expectedArray = [
            [
                'type' => '$notIn',
                'target' => 'foo',
                'value' => ['bar', 'baz'],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder like', function () {
    it('can build a builder with like filter', function () {
        $builder = new Builder();

        $builder->like('foo', 'bar')
            ->like('foo', 123)
            ->like('foo', 45.67);

        expect($builder)->toHaveCount(3);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->like('foo', 'bar');

        $expectedArray = [
            [
                'type' => '$like',
                'target' => 'foo',
                'value' => 'bar',
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder not like', function () {
    it('can build a builder with not like filter', function () {
        $builder = new Builder();

        $builder->notLike('foo', 'bar')
            ->notLike('foo', 123)
            ->notLike('foo', 45.67);

        expect($builder)->toHaveCount(3);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->notLike('foo', 'bar');

        $expectedArray = [
            [
                'type' => '$notLike',
                'target' => 'foo',
                'value' => 'bar',
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder greater than', function () {
    it('can build a builder with greater than filter', function () {
        $builder = new Builder();

        $builder->gt('foo', 123)
            ->gt('foo', 45.67);

        expect($builder)->toHaveCount(2);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->gt('foo', 123);

        $expectedArray = [
            [
                'type' => '$gt',
                'target' => 'foo',
                'value' => 123,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder greater than equal to', function () {
    it('can build a builder with greater than equal to filter', function () {
        $builder = new Builder();

        $builder->gte('foo', 123)
            ->gte('foo', 45.67);

        expect($builder)->toHaveCount(2);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->gte('foo', 123);

        $expectedArray = [
            [
                'type' => '$gte',
                'target' => 'foo',
                'value' => 123,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder less than', function () {
    it('can build a builder with less than filter', function () {
        $builder = new Builder();

        $builder->lt('foo', 123)
            ->lt('foo', 45.67);

        expect($builder)->toHaveCount(2);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->lt('foo', 123);

        $expectedArray = [
            [
                'type' => '$lt',
                'target' => 'foo',
                'value' => 123,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder less than equal to', function () {
    it('can build a builder with less than equal to filter', function () {
        $builder = new Builder();

        $builder->lte('foo', 123)
            ->lte('foo', 45.67);

        expect($builder)->toHaveCount(2);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->lte('foo', 123);

        $expectedArray = [
            [
                'type' => '$lte',
                'target' => 'foo',
                'value' => 123,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});
