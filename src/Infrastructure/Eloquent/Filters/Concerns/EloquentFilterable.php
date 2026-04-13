<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Eloquent\Filters\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Lava83\LaravelDdd\Infrastructure\Eloquent\Filters\EloquentQueryFilter;

trait EloquentFilterable
{
    #[Scope]
    protected function filtering(Builder $query, EloquentQueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
