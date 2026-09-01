<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteRelatedModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantSaveModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ConcurrencyException;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions\EntityClassNotAvailable;
use Lava83\LaravelDdd\Infrastructure\Services\DomainEventPublisher;
use ReflectionException;

/**
 * @template TModel of Model
 * @template TAggregate of Aggregate<TModel, *>
 */
abstract class Repository
{
    /**
     * @var class-string<TAggregate>|null
     */
    protected ?string $entityClassName = null;

    /**
     * @return EntityMapper<TAggregate, TModel>
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function entityMapper(): EntityMapper
    {
        if (blank($this->entityClassName)) {
            throw EntityClassNotAvailable::make($this::class);
        }

        /** @var EntityMapper<TAggregate, TModel> $mapper */
        $mapper = app(EntityMapperResolverContract::class)
            ->resolve($this->entityClassName);

        return $mapper;
    }

    /**
     * @param  TAggregate  $aggregate
     * @return TModel
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     * @throws ReflectionException
     */
    protected function saveEntity(Aggregate $aggregate): Model
    {
        $model = $this->makeModel($aggregate);

        if ($aggregate->isDirty() || $model->exists === false) {
            $this->persistDirtyEntity($aggregate, $model);
        }

        return $model;
    }

    /**
     * @param  TAggregate  $aggregate
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function deleteEntity(Aggregate $aggregate): void
    {
        $model = $this->makeModel($aggregate);

        if (! $model->delete()) {
            throw new CantDeleteModel('Failed to delete entity');
        }

        $this->dispatchUncommittedEvents($aggregate);
    }

    /**
     * @param  Collection<int, TAggregate>  $entities
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function deleteEntities(Collection $entities): void
    {
        $entities->each(fn (Aggregate $entity): null => $this->deleteEntity($entity));
    }

    /**
     * @param  TAggregate  $aggregate
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    protected function deleteRelatedEntity(Aggregate $aggregate, string $relation, int|string $relatedId): void
    {
        $model = $this->makeModel($aggregate);

        $related = $model->$relation()->find($relatedId);

        if (
            $related instanceof Model === false
        ) {
            throw new CantDeleteRelatedModel(sprintf('Relation %s is not a valid Eloquent relation', $relation));
        }

        if (! $related->delete()) {
            throw new CantDeleteRelatedModel('Failed to delete related entity via relation '.$relation);
        }

        $this->dispatchUncommittedEvents($aggregate);
    }

    /**
     * @param  TAggregate  $aggregate
     */
    protected function dispatchUncommittedEvents(Aggregate $aggregate): void
    {
        if ($aggregate->hasUncommittedEvents()) {
            app(DomainEventPublisher::class)->publishEvents($aggregate->uncommittedEvents());
            $aggregate->markEventsAsCommitted();
        }
    }

    /**
     * @param  TAggregate  $aggregate
     * @param  TModel  $model
     *
     * @throws ReflectionException
     */
    protected function syncEntityFromModel(Aggregate $aggregate, Model $model): void
    {
        $aggregate->hydrate($model);
    }

    /**
     * @param  TAggregate  $aggregate
     * @param  TModel  $model
     */
    protected function updateWithVersionGuard(Aggregate $aggregate, Model $model): void
    {
        $base = $aggregate->persistedVersion();
        $model->setAttribute('version', $base + 1);

        $affected = $model->newQuery()
            ->whereKey($model->getKey())
            ->where('version', $base)
            ->update($model->getDirty());

        if ($affected !== 1) {
            throw new ConcurrencyException(sprintf(
                'Entity %s was modified by another process. Expected version %d.',
                $aggregate->id()->value(),
                $base,
            ));
        }

        $model->syncOriginal();
    }

    /**
     * @param  TAggregate  $aggregate
     * @param  TModel  $model
     *
     * @throws ReflectionException
     */
    private function persistDirtyEntity(
        Aggregate $aggregate,
        Model $model,
    ): void {
        if ($model->exists) {
            $this->updateWithVersionGuard($aggregate, $model);
        } else {
            if (! $model->save()) {
                throw new CantSaveModel('Failed to save entity');
            }
        }

        $this->syncEntityFromModel($aggregate, $model);
        $this->dispatchUncommittedEvents($aggregate);
    }

    /**
     * @param  TAggregate  $aggregate
     * @return TModel
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws EntityClassNotAvailable
     */
    protected function makeModel(Aggregate $aggregate): Model
    {
        return $this->entityMapper()->toModel($aggregate);
    }
}
