<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;

/**
 * @extends EntityMapper<CounterTestSubject, CounterTestModel>
 */
final class CounterTestMapper extends EntityMapper
{
    protected static ?string $modelClass = CounterTestModel::class;

    public static function toEntity(Model $model, bool $deep = false): Entity
    {
        return CounterTestSubject::fromState($model);
    }

    public static function toModel(Entity $entity): Model
    {
        return self::findOrCreateModelFillData($entity, [
            'name' => $entity instanceof CounterTestSubject ? $entity->name() : '',
        ]);
    }
}
