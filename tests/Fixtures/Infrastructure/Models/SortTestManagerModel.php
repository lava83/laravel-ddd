<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * On the list's connection, with a `status_id` like the accounts, so a join
 * would make an unqualified `status_id` ambiguous.
 */
final class SortTestManagerModel extends Model
{
    use SoftDeletes;

    protected $table = 'sort_test_managers';

    public $timestamps = false;
}
