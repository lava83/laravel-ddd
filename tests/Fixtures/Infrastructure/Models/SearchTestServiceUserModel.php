<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Its profile and roles name no connection, so they live on this one.
 */
final class SearchTestServiceUserModel extends Model
{
    use SoftDeletes;

    protected $connection = 'search_services';

    protected $table = 'users';

    public $timestamps = false;

    /**
     * @return HasOne<SearchTestServiceProfileModel, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(SearchTestServiceProfileModel::class, 'user_id');
    }

    /**
     * @return BelongsToMany<SearchTestServiceRoleModel, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SearchTestServiceRoleModel::class, 'role_user', 'user_id', 'role_id');
    }
}
