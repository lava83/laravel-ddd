<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;

/**
 * In-memory mapper for EntityTestSubject <-> EntityTestModel.
 *
 * Maps by hand instead of through the base findOrCreateModel* helpers, which
 * query the database — the package harness has none (see .claude/tests.md). That
 * keeps Entity::toState() / entityMapper() testable without persistence.
 *
 * @extends EntityMapper<EntityTestSubject, EntityTestModel>
 */
final class EntityTestMapper extends EntityMapper
{
    public static function toEntity(Model $model, bool $deep = false): Entity
    {
        return EntityTestSubject::fromState($model);
    }

    public static function toModel(Entity $entity): Model
    {
        $model = new EntityTestModel;

        $model->forceFill([
            'id' => $entity->id()->value(),
            'name' => $entity instanceof EntityTestSubject ? $entity->name() : null,
            'version' => $entity->version(),
            'created_at' => $entity->createdAt(),
            'updated_at' => $entity->updatedAt(),
        ]);

        return $model;
    }
}
