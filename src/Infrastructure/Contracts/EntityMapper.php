<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * @template TEntity of Entity<*,*>
 * @template TModel of Model
 */
interface EntityMapper
{
    /**
     * @param  TModel  $model
     * @return TEntity
     */
    public static function toEntity(Model $model, bool $deep = false): Entity;

    /**
     * @param  TEntity  $entity
     * @return TModel
     */
    public static function toModel(Entity $entity): Model;
}
