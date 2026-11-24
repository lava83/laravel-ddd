<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class SortTestIdentifierModel extends Model
{
    protected $table = 'sort_test_identifiers';

    public $timestamps = false;
}
