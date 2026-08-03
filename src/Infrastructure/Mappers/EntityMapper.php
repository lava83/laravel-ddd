<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper as EntityMapperContract;
use Lava83\LaravelDdd\Infrastructure\Mappers\Exceptions\ModelClassNotDefined;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * @template TEntity of Entity<*,*>
 * @template TModel of Model
 *
 * @implements EntityMapperContract<TEntity, TModel>
 *
 * @property-read ?class-string<TModel> $modelClass
 */
abstract class EntityMapper implements EntityMapperContract
{
    protected static ?string $modelClass = null;

    /**
     * @param  TEntity  $entity
     * @param  array<string, string>  $data
     * @return TModel
     */
    protected static function findOrCreateModelFillData(
        Entity $entity,
        array $data,
    ): Model {
        static::ensureModelClassIsDefined();

        $model = static::findOrCreateModel($entity);

        $model->fill(self::mergeWithDefaultData($entity, $data));

        return $model;
    }

    /**
     * @param  TEntity  $entity
     * @return TModel
     */
    protected static function findOrCreateModel(
        Entity $entity,
    ): Model {
        static::ensureModelClassIsDefined();

        /**
         * @var TModel<TEntity> $model
         */
        $model = app(static::$modelClass)
            ->newQuery()
            ->findOr($entity->id(), ['*'], fn () => app(static::$modelClass));

        return $model;
    }

    protected static function ensureModelClassIsDefined(): void
    {
        if (static::$modelClass === null) {
            throw new ModelClassNotDefined('Model class is not defined.');
        }
    }

    /**
     * @param  TEntity  $entity
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private static function mergeWithDefaultData(Entity $entity, array $data): array
    {
        return array_merge([
            app(static::$modelClass)->getKeyName() => (string) $entity->id(),
            'version' => (string) $entity->version(),
            'created_at' => (string) $entity->createdAt(),
            'updated_at' => (string) $entity->updatedAt(),
        ], $data);
    }
}
