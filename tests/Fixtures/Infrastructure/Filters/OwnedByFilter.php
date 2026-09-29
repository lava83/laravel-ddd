<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Illuminate\Database\Eloquent\Builder;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterMethod;
use IndexZer0\EloquentFiltering\Filter\Traits\FilterMethod\FilterContext\CustomFilter;

/**
 * Virtual `$ownedBy` filter: matches rows whose owner_id equals a scalar
 * or is contained in a list.
 */
final class OwnedByFilter implements FilterMethod
{
    use CustomFilter;

    /**
     * @param  int|array<int, int>  $value
     */
    public function __construct(private readonly int|array $value) {}

    public static function type(): string
    {
        return '$ownedBy';
    }

    public function apply(Builder $query): Builder
    {
        return $query->whereIn(
            $this->eloquentContext()->qualifyColumn('owner_id'),
            (array) $this->value,
        );
    }
}
