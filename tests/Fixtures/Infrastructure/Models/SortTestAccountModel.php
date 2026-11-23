<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The list of the relation sort tests, on the default connection. Its
 * customer users and profiles live on `sort_customers`, its managers and
 * identifiers next to it.
 */
final class SortTestAccountModel extends Model
{
    protected $table = 'sort_test_accounts';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<SortTestCustomerUserModel, $this>
     */
    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(SortTestCustomerUserModel::class, 'customer_user_id');
    }

    /**
     * @return BelongsTo<SortTestManagerModel, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(SortTestManagerModel::class, 'manager_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasOne<SortTestProfileModel, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(SortTestProfileModel::class, 'account_id');
    }

    /**
     * @return HasOne<SortTestProfileModel, $this>
     */
    public function latestProfile(): HasOne
    {
        return $this->hasOne(SortTestProfileModel::class, 'account_id')->latestOfMany();
    }

    /**
     * At most one per account; two are a data error.
     *
     * @return HasOne<SortTestIdentifierModel, $this>
     */
    public function activeEmailIdentifier(): HasOne
    {
        return $this->hasOne(SortTestIdentifierModel::class, 'account_id')
            ->where('driver', 'email')
            ->where('active', true);
    }

    /**
     * @return HasOne<SortTestIdentifierModel, $this>
     */
    public function latestIdentifier(): HasOne
    {
        return $this->hasOne(SortTestIdentifierModel::class, 'account_id')->latestOfMany();
    }

    /**
     * @return HasMany<SortTestIdentifierModel, $this>
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(SortTestIdentifierModel::class, 'account_id');
    }

    /**
     * @return BelongsToMany<SortTestManagerModel, $this>
     */
    public function deputies(): BelongsToMany
    {
        return $this->belongsToMany(SortTestManagerModel::class, 'sort_test_account_deputy', 'account_id', 'manager_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
