<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pinned to the `facet_test_remote` connection, which the facet tests
 * configure but never query.
 */
final class FacetTestRemoteNoteModel extends Model
{
    protected $connection = 'facet_test_remote';

    protected $table = 'facet_test_notes';

    public $timestamps = false;
}
