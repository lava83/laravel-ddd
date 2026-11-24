<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * The managers behind a global scope that selects a column of its own, as a
 * scope that adds a computed column does. Not soft-deleting.
 *
 * @extends Model<*>
 */
final class SortTestScopedManagerModel extends Model
{
    protected $table = 'sort_test_managers';

    public $timestamps = false;

    protected static function booted(): void
    {
        self::addGlobalScope('status', fn (Builder $query) => $query->addSelect('sort_test_managers.status_id'));
    }
}
