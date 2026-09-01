<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;
use IndexZer0\EloquentFiltering\Contracts\IsFilterable;
use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;
use IndexZer0\EloquentFiltering\Filter\Traits\Filterable;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;
use Lava83\LaravelDdd\Infrastructure\Models\Exceptions\EntityClassNotAvailable;

/**
 * @template TEntity of Entity<*,*>
 *
 * @property int $version
 * @property-read Carbon $created_at
 * @property-read ?Carbon $updated_at
 *
 * @method static findOr($id, $columns = ['*'], Closure $callback = null)
 */
abstract class Model extends EloquentModel implements IsFilterable
{
    use Filterable;

    /**
     * @var class-string<TEntity>|null
     */
    protected ?string $entityClassName = null;

    public function allowedFilters(): AllowedFilterList
    {
        return Filter::none();
    }

    public function getFillable(): array
    {
        return array_merge(['id', 'version', 'created_at', 'updated_at'], $this->fillable);
    }

    /**
     * @return TEntity
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    public function toEntity(): Entity
    {
        return $this->entityMapper()
            ->toEntity($this);
    }

    /**
     * @return EntityMapper<TEntity, static>
     *
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    public function entityMapper(): EntityMapper
    {
        if (blank($this->entityClassName)) {
            throw EntityClassNotAvailable::make($this::class);
        }

        /** @var EntityMapper<TEntity, static> $mapper */
        $mapper = app(EntityMapperResolverContract::class)
            ->resolve($this->entityClassName);

        return $mapper;
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
