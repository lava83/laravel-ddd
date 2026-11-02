<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FacetTestNoteModel extends Model
{
    use SoftDeletes;

    protected $table = 'facet_test_notes';

    public $timestamps = false;

    protected $guarded = [];
}
