<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models;

use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Carbon;
use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Traits\Filterable;
use Lava83\LaravelDdd\Infrastructure\Models\Concerns\HasUuids;

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
    use HasUuids;
    use Filterable;

    public function allowedFilters(): ?AllowedFilterList
    {
        return null;
    }

    public function getFillable(): array
    {
        return array_merge(['id', 'version', 'created_at', 'updated_at'], $this->fillable);
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
