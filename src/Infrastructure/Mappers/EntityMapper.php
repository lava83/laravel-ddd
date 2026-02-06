<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Laravel\Sanctum\PersonalAccessToken;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper as EntityMapperContract;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

abstract class EntityMapper implements EntityMapperContract
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $data
     */
    protected static function findOrCreateModelFillData(
        Entity $entity,
        string $modelClass,
        array $data,
    ): Model|EloquentModel|PersonalAccessToken {
        $model = static::findOrCreateModel($entity, $modelClass);

        $model->fill(self::mergeWithDefaultData($entity, $data));

        return $model;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected static function findOrCreateModel(
        Entity $entity,
        string $modelClass,
    ): Model|EloquentModel|PersonalAccessToken {
        return app($modelClass)->findOr($entity->id(), ['*'], fn() => app($modelClass));
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private static function mergeWithDefaultData(Entity $entity, array $data): array
    {
        return array_merge([
            'id' => (string) $entity->id(),
            'version' => (string) $entity->version(),
            'created_at' => (string) $entity->createdAt(),
            'updated_at' => (string) $entity->updatedAt(),
        ], $data);
    }
}
