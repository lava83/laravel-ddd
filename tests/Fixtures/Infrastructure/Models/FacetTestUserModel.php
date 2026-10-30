<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use IndexZer0\EloquentFiltering\Filter\Contracts\AllowedFilterList;
use IndexZer0\EloquentFiltering\Filter\Filterable\Filter;
use IndexZer0\EloquentFiltering\Filter\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * The list model of the facet tests: soft-deletable, with a nullable
 * `status_id`, and one relation for each kind FacetCounter accepts or rejects.
 *
 * @extends Model<*>
 */
final class FacetTestUserModel extends Model
{
    use SoftDeletes;

    protected $table = 'facet_test_users';

    protected $fillable = ['email', 'branch_id', 'status_id', 'type_id'];

    public function allowedFilters(): AllowedFilterList
    {
        return Filter::only(
            Filter::field('branch_id', [FilterType::EQUAL, FilterType::IN]),
            Filter::field('status_id', [FilterType::EQUAL, FilterType::IN]),
            Filter::field('type_id', [FilterType::EQUAL, FilterType::IN]),
        );
    }

    /**
     * @return HasMany<FacetTestNoteModel, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(FacetTestNoteModel::class, 'user_id');
    }

    /**
     * @return BelongsToMany<FacetTestServiceModel, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(FacetTestServiceModel::class, 'facet_test_service_user', 'user_id', 'service_id');
    }

    /**
     * Lives on another connection.
     *
     * @return HasMany<FacetTestRemoteNoteModel, $this>
     */
    public function remoteNotes(): HasMany
    {
        return $this->hasMany(FacetTestRemoteNoteModel::class, 'user_id');
    }

    /**
     * @return HasOne<FacetTestNoteModel, $this>
     */
    public function note(): HasOne
    {
        return $this->hasOne(FacetTestNoteModel::class, 'user_id');
    }

    /**
     * @return MorphToMany<FacetTestServiceModel, $this>
     */
    public function taggedServices(): MorphToMany
    {
        return $this->morphToMany(FacetTestServiceModel::class, 'taggable', 'facet_test_taggables');
    }
}
