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
     * @param  Collection<class-string<Entity>, EntityMapper>  $mappers
     */
    public function __construct(
        private Collection $mappers = new Collection,
    ) {}

    /**
     * Summary of registerMapper
     *
     * @param  class-string<Entity>  $entityClass
     */
    public function registerMapper(string $entityClass, EntityMapper $mapper): void
    {
        $this->mappers->put($entityClass, $mapper);
    }

    /**
     * Resolve the appropriate mapper for the given entity.
     *
     * @param  class-string<Entity>  $entityClass
     *
     * @throws NoMapperFoundForEntity
     */
    public function resolve(string $entityClass): EntityMapper
    {
        if ($this->mappers->has($entityClass)) {
            return $this->mappers->get($entityClass);
        }

        throw NoMapperFoundForEntity::make($entityClass);
    }
}
