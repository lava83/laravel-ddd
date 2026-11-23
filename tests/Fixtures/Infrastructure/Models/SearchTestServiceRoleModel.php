<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchTestServiceRoleModel extends Model
{
    protected $table = 'roles';

    public $timestamps = false;
}
