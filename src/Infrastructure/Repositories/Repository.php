<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolver;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteRelatedModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantSaveModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ConcurrencyException;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Infrastructure\Services\DomainEventPublisher;

abstract class Repository
{
    /**
     * @property class-string<Aggregate> $aggregateClass
     */
    protected string $aggregateClass;

    public function __construct(
        protected EntityMapperResolver $mapperResolver,
    ) {
        // @todo implement ensuring of aggregate class being set and is a subclass of Aggregate
    }

    protected function entityMapper(): EntityMapper
    {
        return $this->mapperResolver->resolve($this->aggregateClass);
    }

    protected function saveEntity(Entity|Aggregate $entity): Model|Authenticatable|PersonalAccessToken
    {
        $model = $this->mapperResolver->resolve($entity::class)->toModel($entity);

        if ($entity->isDirty() || $model->exists === false) {
            $this->persistDirtyEntity($entity, $model);
        }

        $this->syncEntityFromModel($entity, $model);

        return $model;
    }

    protected function deleteEntity(Entity|Aggregate $entity): void
    {
        $model = $this->mapperResolver->resolve($entity::class)->toModel($entity);

        if (!$model->delete()) {
            throw new CantDeleteModel('Failed to delete entity');
        }

        if ($entity instanceof Aggregate) {
            $this->dispatchUncommittedEvents($entity);
        }
    }

    /**
     * @param Collection<int, Entity|Aggregate> $entities
     */
    protected function deleteEntities(Collection $entities): void
    {
        $entities->map(fn(Entity|Aggregate $entity): null => $this->deleteEntity($entity));
    }

    protected function deleteRelatedEntity(Entity|Aggregate $entity, string $relation, int|string $relatedId): void
    {
        $model = $this->mapperResolver->resolve($entity::class)->toModel($entity);

        // @mago-expect analyzer:mixed-assignment,mixed-method-access,string-member-selector
        $related = $model->$relation()->find($relatedId);

        if (
            $related instanceof Model === false
            && $related instanceof Authenticatable === false
            && $related instanceof PersonalAccessToken === false
        ) {
            throw new CantDeleteRelatedModel(sprintf('Relation %s is not a valid Eloquent relation', $relation));
        }

        if (!$related->delete()) {
            throw new CantDeleteRelatedModel('Failed to delete related entity via relation ' . $relation);
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

    protected function handleOptimisticLocking(Model|Authenticatable|PersonalAccessToken $model, Entity $entity): void
    {
        $expectedDatabaseVersion = $entity->version();
        $modelVersion = $model instanceof Model
            ? $model->version
            : (int) ($model->getAttribute('version') ?? 0);


        if ($modelVersion !== $expectedDatabaseVersion) {
            throw new ConcurrencyException(sprintf(
                'Entity %s was modified by another process. Expected version: %d, Actual version: %d',
                $entity->id()->value(),
                $expectedDatabaseVersion,
                $modelVersion,
            ));
        }
    }

    protected function syncEntityFromModel(Entity $entity, Model|Authenticatable|PersonalAccessToken $model): void
    {
        // @mago-expect analyzer:possibly-invalid-argument
        $entity->hydrate($model);
    }

    private function persistDirtyEntity(Entity|Aggregate $entity, Model|Authenticatable|PersonalAccessToken $model): void
    {
        if ($model->exists) {
            $this->handleOptimisticLocking($model, $entity);
        }

        if (!$model->save()) {
            throw new CantSaveModel('Failed to save entity');
        }

        if ($entity instanceof Aggregate) {
            $this->dispatchUncommittedEvents($entity);
        }
    }
}
