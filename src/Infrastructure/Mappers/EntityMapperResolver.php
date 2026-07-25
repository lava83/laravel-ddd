<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;
use Lava83\LaravelDdd\Infrastructure\Mappers\Exceptions\NoMapperFoundForEntity;

readonly class EntityMapperResolver implements EntityMapperResolverContract
{
    /**
     * @param  Collection<class-string<Entity<*, *>>, EntityMapper<*, *>>  $mappers
     */
    public function __construct(
        private Collection $mappers = new Collection,
    ) {}

    /**
     * Summary of registerMapper
     *
     * @param  class-string<Entity<*, *>>  $entityClass
     * @param  EntityMapper<*, *>  $mapper
     */
    public function registerMapper(string $entityClass, EntityMapper $mapper): self
    {
        $this->mappers->put($entityClass, $mapper);

        return $this;
    }

    /**
     * Resolve the appropriate mapper for the given entity.
     *
     * @template TEntity of Entity<*, *>
     *
     * @param  class-string<TEntity>  $entityClass
     * @return EntityMapper<TEntity, *>
     *
     * @throws NoMapperFoundForEntity
     */
    public function resolve(string $entityClass): EntityMapper
    {
        if ($this->mappers->has($entityClass)) {
            /**
             * @var EntityMapper<TEntity, *> $entityMapper
             */
            $entityMapper = $this->mappers->get($entityClass);

            return $entityMapper;
        }

        throw NoMapperFoundForEntity::make($entityClass);
    }
}
