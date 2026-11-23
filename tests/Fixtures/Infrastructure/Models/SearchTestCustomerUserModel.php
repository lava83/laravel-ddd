<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchTestCustomerUserModel extends Model
{
    protected $connection = 'search_customers';

    protected $table = 'users';

    public $timestamps = false;
}
