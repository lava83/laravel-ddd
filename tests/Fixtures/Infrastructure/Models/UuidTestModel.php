<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Infrastructure\Models\Concerns\HasUuids;

/**
 * Plain Eloquent model that opts into the package's UUIDv7 keys.
 */
final class UuidTestModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'uuid_test_items';

    protected $guarded = [];
}
