<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\BetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThan;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThanEqualTo;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\In;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNotNull;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNull;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThan;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThanEqualTo;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Like;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetween;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotEqual;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotIn;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotLike;

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
            ->and($builder->filters()->first())->toBeInstanceOf(Equal::class);
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

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(NotEqual::class);
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

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(In::class);
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

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(NotIn::class);
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

        expect($builder)->toHaveCount(3)
            ->and($builder->filters()->first())->toBeInstanceOf(Like::class);
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

        expect($builder)->toHaveCount(3)
            ->and($builder->filters()->first())->toBeInstanceOf(NotLike::class);
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

        expect($builder)->toHaveCount(2)
            ->and($builder->filters()->first())->toBeInstanceOf(GreaterThan::class);
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

        expect($builder)->toHaveCount(2)
            ->and($builder->filters()->first())->toBeInstanceOf(GreaterThanEqualTo::class);
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

        expect($builder)->toHaveCount(2)
            ->and($builder->filters()->first())->toBeInstanceOf(LessThan::class);
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

        expect($builder)->toHaveCount(2)
            ->and($builder->filters()->first())->toBeInstanceOf(LessThanEqualTo::class);
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

describe('Builder is null', function () {
    it('can build a builder with is null filter', function () {
        $builder = new Builder();

        $builder->isNull('foo');

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(IsNull::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->isNull('foo');

        $expectedArray = [
            [
                'type' => '$null',
                'target' => 'foo',
                'value' => true,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder is not null', function () {
    it('can build a builder with is not null filter', function () {
        $builder = new Builder();

        $builder->isNotNull('foo');

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(IsNotNull::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->isNotNull('foo');

        $expectedArray = [
            [
                'type' => '$null',
                'target' => 'foo',
                'value' => false,
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder between', function () {
    it('can build a builder with between filter', function () {
        $builder = new Builder();

        $builder->between('foo', [10, 20]);

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(Between::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->between('foo', [10, 20]);

        $expectedArray = [
            [
                'type' => '$between',
                'target' => 'foo',
                'value' => [10,20],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder not between', function () {
    it('can build a builder with not between filter', function () {
        $builder = new Builder();

        $builder->notBetween('foo', [10, 20]);

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(NotBetween::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->notBetween('foo', [10, 20]);

        $expectedArray = [
            [
                'type' => '$notBetween',
                'target' => 'foo',
                'value' => [10,20],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder between columns', function () {
    it('can build a builder with between columns filter', function () {
        $builder = new Builder();

        $builder->betweenColumns('foo', ['foo', 'bar']);

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(BetweenColumns::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->betweenColumns('foo', ['bar', 'baz']);

        $expectedArray = [
            [
                'type' => '$betweenColumns',
                'target' => 'foo',
                'value' => ['bar','baz'],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});

describe('Builder not between columns', function () {
    it('can build a builder with not between columns filter', function () {
        $builder = new Builder();

        $builder->notBetweenColumns('foo', ['bar', 'baz']);

        expect($builder)->toHaveCount(1)
            ->and($builder->filters()->first())->toBeInstanceOf(NotBetweenColumns::class);
    });

    it('can convert to array', function () {
        $builder = new Builder();

        $builder->notBetweenColumns('foo', ['bar', 'baz']);

        $expectedArray = [
            [
                'type' => '$notBetweenColumns',
                'target' => 'foo',
                'value' => ['bar','baz'],
            ],
        ];

        expect($builder->toArray())->toBe($expectedArray);
    });
});
