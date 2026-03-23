<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class EloquentQueryFilter
{
    /**
     * @return array<string, callable(Builder): Builder>
     */
    abstract protected function filters(): array;

    abstract public static function fromArray(array $data): static;

    public function apply(Builder $query): Builder
    {
        foreach ($this->filters() as $callable) {
            $query = $callable($query);
        }

        return $query;
    }
}
