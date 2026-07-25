<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Lava83\LaravelDdd\Domain\Entities\Entity;

interface EntityMapperResolverContract
{
    /**
     * @template TEntity of Entity<*, *>
     *
     * @param  class-string<TEntity>  $entityClass
     * @return EntityMapper<TEntity, *>
     */
    public function resolve(string $entityClass): EntityMapper;

    /**
     * Summary of registerMapper
     *
     * @param  class-string<Entity<*, *>>  $entityClass
     * @param  EntityMapper<*, *>  $mapper
     */
    public function registerMapper(string $entityClass, EntityMapper $mapper): self;
}
