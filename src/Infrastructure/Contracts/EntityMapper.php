<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

interface EntityMapper
{
    public static function toEntity(Model $model, bool $deep = false): Entity;

    public static function toModel(Entity $entity): Model|Authenticatable;
}
