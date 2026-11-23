<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The list of the cross-connection search tests, on the default connection.
 * Its users and notes live on `search_customers` and `search_services`.
 */
final class SearchTestAccountModel extends Model
{
    protected $table = 'search_test_accounts';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<SearchTestCustomerUserModel, $this>
     */
    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(SearchTestCustomerUserModel::class, 'customer_user_id');
    }

    /**
     * @return BelongsTo<SearchTestServiceUserModel, $this>
     */
    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(SearchTestServiceUserModel::class, 'service_user_id');
    }

    /**
     * @return HasMany<SearchTestAccountNoteModel, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(SearchTestAccountNoteModel::class, 'account_id');
    }

    /**
     * @return HasOne<SearchTestAccountNoteModel, $this>
     */
    public function latestNote(): HasOne
    {
        return $this->hasOne(SearchTestAccountNoteModel::class, 'account_id')->latestOfMany();
    }

    /**
     * @return MorphMany<SearchTestAccountNoteModel, $this>
     */
    public function attachedNotes(): MorphMany
    {
        return $this->morphMany(SearchTestAccountNoteModel::class, 'noteable');
    }

    /**
     * @return BelongsToMany<SearchTestServiceUserModel, $this>
     */
    public function serviceUsers(): BelongsToMany
    {
        return $this->belongsToMany(SearchTestServiceUserModel::class, 'account_user', 'account_id', 'user_id');
    }
}
