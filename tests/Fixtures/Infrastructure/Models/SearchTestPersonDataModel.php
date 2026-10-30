<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchTestPersonDataModel extends Model
{
    protected $table = 'search_test_person_data';

    public $timestamps = false;

    protected $guarded = [];
}
