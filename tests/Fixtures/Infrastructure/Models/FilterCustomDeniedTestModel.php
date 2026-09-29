<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Keeps the base `Filter::none()`, so the custom `$ownedBy` filter is denied.
 *
 * @extends Model<*>
 */
final class FilterCustomDeniedTestModel extends Model
{
    protected $table = 'filter_custom_test_items';

    protected $fillable = ['name', 'owner_id'];
}
