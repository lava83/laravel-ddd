<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SortTestProfileModel extends Model
{
    use SoftDeletes;

    protected $connection = 'sort_customers';

    protected $table = 'profiles';

    public $timestamps = false;
}
