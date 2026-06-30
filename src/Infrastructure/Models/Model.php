<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;
use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Traits\Filterable;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;
use Lava83\LaravelDdd\Infrastructure\Models\Exceptions\EntityClassNotAvailable;

/**
 * @property int $version *
 * @property-read Carbon $created_at
 * @property-read ?Carbon $updated_at
 *
 * @method Model findOr($id, $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Model newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Model newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Model query()
 */
abstract class Model extends EloquentModel
{
    use Filterable;

    /**
     * @var class-string<Entity>|null
     */
    protected ?string $entityClassName = null;

    public function allowedFilters(): ?AllowedFilterList
    {
        return null;
    }

    public function getFillable(): array
    {
        return array_merge(['id', 'version', 'created_at', 'updated_at'], $this->fillable);
    }

    /**
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
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     * @throws EntityClassNotAvailable
     */
    public function entityMapper(): EntityMapper
    {
        if (blank($this->entityClassName)) {
            throw EntityClassNotAvailable::make($this::class);
        }

        return app(EntityMapperResolverContract::class)
            ->resolve($this->entityClassName);
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
