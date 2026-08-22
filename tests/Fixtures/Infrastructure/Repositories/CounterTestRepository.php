<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories;

use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;

/**
 * @extends Repository<CounterTestModel, CounterTestSubject>
 */
final class CounterTestRepository extends Repository
{
    /** @var class-string<CounterTestSubject> */
    protected ?string $entityClassName = CounterTestSubject::class;

    public function save(CounterTestSubject $aggregate): CounterTestModel
    {
        /** @var CounterTestModel $model */
        $model = $this->saveEntity($aggregate);

        return $model;
    }

    public function find(int $id): ?CounterTestSubject
    {
        $model = CounterTestModel::query()->find($id);

        if ($model === null) {
            return null;
        }

        /** @var CounterTestSubject $entity */
        $entity = $this->entityMapper()->toEntity($model);

        return $entity;
    }
}
