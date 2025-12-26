<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper as EntityMapperContract;

abstract class EntityMapper implements EntityMapperContract
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     */
    protected static function findOrCreateModelFillData(Entity $entity, string $modelClass, array $data): Model
    {
        $model = static::findOrCreateModel($entity, $modelClass);

        $model->fill(self::mergeWithDefaultData($entity, $data));

        return $model;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected static function findOrCreateModel(Entity $entity, string $modelClass): Model
    {
        return app($modelClass)->findOr($entity->id(), ['*'], fn () => app($modelClass));
    }

    private static function mergeWithDefaultData(Entity $entity, array $data): array
    {
        return array_merge(
            [
                'id' => $entity->id(),
                'version' => $entity->version(),
                'created_at' => $entity->createdAt(),
                'updated_at' => $entity->updatedAt(),
            ],
            $data
        );
    }
}
