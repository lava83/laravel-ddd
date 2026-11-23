<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Illuminate\Database\Eloquent\Builder;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterMethod;
use IndexZer0\EloquentFiltering\Filter\Traits\FilterMethod\FilterContext\CustomFilter;

/**
 * Virtual `$eqStatus` filter on the facet test users: the custom filter a
 * status facet names as its own, which a client may send without a target.
 */
final class FacetTestStatusFilter implements FilterMethod
{
    use CustomFilter;

    public function __construct(private readonly int $value) {}

    public static function type(): string
    {
        return '$eqStatus';
    }

    public function apply(Builder $query): Builder
    {
        return $query->where($this->eloquentContext()->qualifyColumn('status_id'), $this->value);
    }
}
