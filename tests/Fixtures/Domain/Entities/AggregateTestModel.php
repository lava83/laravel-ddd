<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Eloquent model backing AggregateTestSubject::fromState()/hydrate().
 *
 * Non-incrementing string key so the UUID primary key is not int-cast (see
 * Model::casts()). No database is involved: fixtures build instances with
 * newFromBuilder() and read attributes back — never touching a connection.
 *
 * @extends Model<AggregateTestSubject>
 */
final class AggregateTestModel extends Model
{
    public $incrementing = false;

    protected $table = 'aggregate_test_subjects';

    protected $keyType = 'string';

    protected $fillable = ['name'];
}
