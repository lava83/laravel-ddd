<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class CursorTestOwnerModel extends Model
{
    protected $table = 'cursor_test_owners';

    public $timestamps = false;

    protected $guarded = [];
}
