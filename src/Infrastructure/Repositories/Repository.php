<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Integer;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteRelatedModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantSaveModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ConcurrencyException;
use Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions\EntityClassNotAvailable;
use Lava83\LaravelDdd\Infrastructure\Services\DomainEventPublisher;
use ReflectionException;

abstract class Repository
{
    const int DEFAULT_VERSION = 1;

    /**
     * @property class-string<Aggregate>|null $entityClassName
     */
    protected ?string $entityClassName = null;

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function entityMapper(): EntityMapper
    {
        if (blank($this->entityClassName)) {
            throw EntityClassNotAvailable::make($this::class);
        }

        return app(EntityMapperResolverContract::class)
            ->resolve($this->entityClassName);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function saveEntity(Entity|Aggregate $entity): Model
    {
        $model = $this
            ->entityMapper()
            ->toModel($entity);

        if ($entity->isDirty() || $model->exists === false) {
            $this->persistDirtyEntity($entity, $model);
        }

        $this->syncEntityFromModel($entity, $model);

        return $model;
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function deleteEntity(Entity|Aggregate $entity): void
    {
        $model = $this
            ->entityMapper()
            ->toModel($entity);

        if (! $model->delete()) {
            throw new CantDeleteModel('Failed to delete entity');
        }

        if ($entity instanceof Aggregate) {
            $this->dispatchUncommittedEvents($entity);
        }
    }

    /**
     * @param  Collection<int, Entity|Aggregate>  $entities
     */
    protected function deleteEntities(Collection $entities): void
    {
        $entities->map(fn (Entity|Aggregate $entity): null => $this->deleteEntity($entity));
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function deleteRelatedEntity(Entity|Aggregate $entity, string $relation, int|string $relatedId): void
    {
        $model = $this->entityMapper()->toModel($entity);

        $related = $model->$relation()->find($relatedId);

        if (
            $related instanceof Model === false
        ) {
            throw new CantDeleteRelatedModel(sprintf('Relation %s is not a valid Eloquent relation', $relation));
        }

        if (! $related->delete()) {
            throw new CantDeleteRelatedModel('Failed to delete related entity via relation '.$relation);
        }

        if ($entity instanceof Aggregate) {
            $this->dispatchUncommittedEvents($entity);
        }
    }

    protected function dispatchUncommittedEvents(Aggregate $entity): void
    {
        if ($entity->hasUncommittedEvents()) {
            app(DomainEventPublisher::class)->publishEvents($entity->uncommittedEvents());
            $entity->markEventsAsCommitted();
        }
    }

    protected function handleOptimisticLocking(Model $model, Entity $entity): void
    {
        $expectedDatabaseVersion = $entity->version();
        $modelVersion = $model->getAttribute('version') ?? self::DEFAULT_VERSION;

        if ((int) $modelVersion !== (int) $expectedDatabaseVersion) {
            throw new ConcurrencyException(sprintf(
                'Entity %s was modified by another process. Expected version: %d, Actual version: %d',
                $entity->id()->value(),
                $expectedDatabaseVersion,
                $modelVersion,
            ));
        }
    }

    protected function syncEntityFromModel(Entity $entity, Model $model): void
    {
        $entity->hydrate($model);
    }

    /**
     * @throws ReflectionException
     */
    private function persistDirtyEntity(
        Entity|Aggregate $entity,
        Model $model,
    ): void {
        $this->handleOptimisticLocking($model, $entity);

        if (! $model->save()) {
            throw new CantSaveModel('Failed to save entity');
        }

        if ($entity->id() instanceof Integer) {
            $entity->idFromPersistence($entity->id()::fromValue($model->getKey()));
        }

        if ($entity instanceof Aggregate) {
            $this->dispatchUncommittedEvents($entity);
        }
    }
}
