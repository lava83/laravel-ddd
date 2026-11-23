<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class SearchTestAccountNoteModel extends Model
{
    protected $connection = 'search_services';

    protected $table = 'notes';

    public $timestamps = false;
}
