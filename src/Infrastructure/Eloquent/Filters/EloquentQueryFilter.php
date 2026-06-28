<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class EloquentQueryFilter
{
    /**
     * @return array<string, callable(Builder<Model>): Builder<Model>>
     */
    abstract protected function filters(): array;

    /**
     * @param array{
     *     type: string,
     *     target: string,
     *     value: array<int, string|int|float|bool>|string|int|float|bool
     * } $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query): Builder
    {
        foreach ($this->filters() as $callable) {
            $query = $callable($query);
        }

        return $query;
    }
}
