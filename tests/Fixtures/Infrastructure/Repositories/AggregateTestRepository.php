<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;

/**
 * Concrete repository over AggregateTestSubject that exposes the protected
 * building blocks of the base Repository as a public surface, so the suite can
 * drive them directly (mirrors the runGuard()/readModelClass() fixture style).
 *
 * @extends Repository<AggregateTestModel, AggregateTestSubject>
 */
final class AggregateTestRepository extends Repository
{
    /** @var class-string<AggregateTestSubject> */
    protected ?string $entityClassName = AggregateTestSubject::class;

    public function find(EntityTestId $id): ?AggregateTestSubject
    {
        $model = AggregateTestModel::query()->find($id->value());

        if ($model === null) {
            return null;
        }

        /** @var AggregateTestSubject $entity */
        $entity = $this->entityMapper()->toEntity($model);

        return $entity;
    }

    public function save(AggregateTestSubject $aggregate): AggregateTestModel
    {
        /** @var AggregateTestModel $model */
        $model = $this->saveEntity($aggregate);

        return $model;
    }

    public function remove(AggregateTestSubject $aggregate): void
    {
        $this->deleteEntity($aggregate);
    }

    /**
     * @param  Collection<int, AggregateTestSubject>  $aggregates
     */
    public function removeMany(Collection $aggregates): void
    {
        $this->deleteEntities($aggregates);
    }

    public function mapper(): EntityMapper
    {
        return $this->entityMapper();
    }
}
