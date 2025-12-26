<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Lava83\LaravelDdd\Domain\Entities\Entity;

interface EntityMapperResolver
{
    public function resolve(string $entityClass): EntityMapper;

    /**
     * Summary of registerMapper
     *
     * @param  class-string<Entity>  $entityClass
     */
    public function registerMapper(string $entityClass, EntityMapper $mapper): void;
}
