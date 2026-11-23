<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Domain\Exceptions\EntityNotFound;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper as EntityMapperContract;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ModelWasDeleted;
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
     * @param  array<string, mixed>  $data
     * @return TModel
     *
     * @throws EntityNotFound
     * @throws ModelWasDeleted
     */
    protected static function findOrCreateModelFillData(
        Entity $entity,
        array $data,
    ): Model {
        static::ensureModelClassIsDefined();

        $model = static::findOrCreateModel($entity);

        $model->fill(self::mergeWithDefaultData($entity, $data));

        self::releaseAutoIncrementKey($model);

        return $model;
    }

    /**
     * @param  TModel  $model
     */
    protected static function releaseAutoIncrementKey(Model $model): void
    {
        if (
            $model->exists === false
            && $model->getIncrementing()
            && $model->getKeyType() === 'int'
        ) {
            $model->offsetUnset($model->getKeyName());
        }
    }

    /**
     * A fresh model is only for a new entity. One hydrated from a row must find
     * that row again; a fresh model would insert a copy of a deleted row.
     *
     * @param  TEntity  $entity
     * @return TModel
     *
     * @throws EntityNotFound
     * @throws ModelWasDeleted
     */
    protected static function findOrCreateModel(
        Entity $entity,
    ): Model {
        static::ensureModelClassIsDefined();

        if ($entity->isPersisted()) {
            return self::findPersistedModel($entity)->refreshForUpdate();
        }

        /**
         * @var TModel<TEntity> $model
         */
        $model = app(static::$modelClass)
            ->newQuery()
            ->findOr($entity->id(), ['*'], fn () => app(static::$modelClass));

        return $model->refreshForUpdate();
    }

    /**
     * @param  TEntity  $entity
     * @return TModel
     *
     * @throws EntityNotFound
     * @throws ModelWasDeleted
     */
    private static function findPersistedModel(Entity $entity): Model
    {
        /** @var ?TModel $model */
        $model = app(static::$modelClass)
            ->newQuery()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->find($entity->id());

        if ($model === null) {
            throw new EntityNotFound(sprintf(
                'Entity %s no longer exists: its row was deleted after it was loaded.',
                $entity->id()->value(),
            ));
        }

        // trashed() comes with SoftDeletes; a model without it is never trashed.
        if (method_exists($model, 'trashed') && $model->trashed()) {
            throw new ModelWasDeleted(sprintf(
                'Entity %s was deleted after it was loaded: its row is soft-deleted.',
                $entity->id()->value(),
            ));
        }

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function mergeWithDefaultData(Entity $entity, array $data): array
    {
        return array_merge([
            app(static::$modelClass)->getKeyName() => (string) $entity->id(),
            'version' => $entity->version(),
            'created_at' => $entity->createdAt(),
            'updated_at' => $entity->updatedAt(),
        ], $data);
    }
}
