<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper as EntityMapperContract;

/**
 * @template TEntity of Entity<*,*>
 * @template TModel of Model
 *
 * @implements EntityMapperContract<TEntity, TModel>
 */
abstract class EntityMapper implements EntityMapperContract
{
    /**
     * @param  TEntity  $entity
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, string>  $data
     * @return TModel
     */
    protected static function findOrCreateModelFillData(
        Entity $entity,
        string $modelClass,
        array $data,
    ): Model {
        $model = static::findOrCreateModel($entity, $modelClass);

        $model->fill(self::mergeWithDefaultData($entity, $data));

        return $model;
    }

    /**
     * @param  TEntity  $entity
     * @param  class-string<TModel>  $modelClass
     */
    protected static function findOrCreateModel(
        Entity $entity,
        string $modelClass,
    ): Model {
        return app($modelClass)
            ->newQuery()
            ->findOr($entity->id(), ['*'], fn () => app($modelClass));
    }

    /**
     * @param  TEntity  $entity
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
