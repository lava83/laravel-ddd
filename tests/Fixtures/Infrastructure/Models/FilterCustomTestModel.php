<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;
use IndexZer0\EloquentFiltering\Filter\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Allows the custom `$ownedBy` filter and `$eq` on name.
 *
 * @extends Model<*>
 */
final class FilterCustomTestModel extends Model
{
    protected $table = 'filter_custom_test_items';

    protected $fillable = ['name', 'owner_id'];

    public function allowedFilters(): AllowedFilterList
    {
        return Filter::only(
            Filter::custom('$ownedBy'),
            Filter::field('name', [FilterType::EQUAL]),
        );
    }
}
