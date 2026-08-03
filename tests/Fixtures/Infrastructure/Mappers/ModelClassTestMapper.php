<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;

/**
 * Base fixture for the EntityMapper::$modelClass edge cases.
 *
 * Implements the abstract contract methods and exposes the protected static
 * guard and property, so the concrete subclasses can differ by nothing but
 * their $modelClass binding — which is exactly the late-static-binding
 * behaviour under test.
 *
 * @extends EntityMapper<EntityTestSubject, EntityTestModel>
 */
abstract class ModelClassTestMapper extends EntityMapper
{
    public static function toEntity(Model $model, bool $deep = false): Entity
    {
        return EntityTestSubject::fromState($model);
    }

    public static function toModel(Entity $entity): Model
    {
        return static::findOrCreateModelFillData($entity, [
            'name' => $entity instanceof EntityTestSubject ? $entity->name() : '',
        ]);
    }

    public static function readModelClass(): ?string
    {
        return static::$modelClass;
    }

    public static function runGuard(): void
    {
        static::ensureModelClassIsDefined();
    }
}
