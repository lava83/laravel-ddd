<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;
use IndexZer0\EloquentFiltering\Filter\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * The list model of the sort tests: `rank` and `happened_at` repeat, so only
 * the key tie-breaker makes the order unique, and `score` may hold NULL.
 *
 * @extends Model<*>
 */
final class SortTestItemModel extends Model
{
    protected $table = 'sort_test_items';

    public $timestamps = false;

    protected $fillable = ['rank', 'name', 'happened_at', 'score', 'status_id'];

    public function allowedFilters(): AllowedFilterList
    {
        return Filter::only(
            Filter::field('status_id', [FilterType::EQUAL, FilterType::IN]),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }
}
