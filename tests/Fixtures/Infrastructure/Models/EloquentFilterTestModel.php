<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Lava83\LaravelDdd\Infrastructure\Eloquent\Filters\Concerns\EloquentFilterable;

/**
 * Plain Eloquent model that gains the `filtering` scope.
 */
final class EloquentFilterTestModel extends Model
{
    use EloquentFilterable;

    public $timestamps = false;

    protected $table = 'eloquent_filter_items';

    protected $guarded = [];
}
