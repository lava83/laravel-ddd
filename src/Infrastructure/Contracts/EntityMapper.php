<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Domain\Entities\Entity;

interface EntityMapper
{
    public static function toEntity(Model $model, bool $deep = false): Entity;

    public static function toModel(Entity $entity): Model;
}
