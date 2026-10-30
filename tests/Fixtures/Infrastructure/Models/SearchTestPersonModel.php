<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Allows the three search fixtures; first and last name live in `data`.
 *
 * @extends Model<*>
 */
final class SearchTestPersonModel extends Model
{
    protected $table = 'search_test_people';

    protected $fillable = ['email'];

    public function allowedFilters(): AllowedFilterList
    {
        return Filter::only(
            Filter::custom('$personSearch'),
            Filter::custom('$personContainsSearch'),
            Filter::custom('$personTwoTokenSearch'),
        );
    }

    /**
     * @return HasOne<SearchTestPersonDataModel, $this>
     */
    public function data(): HasOne
    {
        return $this->hasOne(SearchTestPersonDataModel::class, 'person_id');
    }
}
