<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Eloquent model backing the hydrate()/fromState() fixtures.
 *
 * Declared non-incrementing with a string key so Eloquent does not int-cast
 * the UUID primary key (see Model::getCasts(), which merges an int cast for
 * incrementing keys).
 *
 * @extends Model<EntityTestSubject>
 */
final class EntityTestModel extends Model
{
    public $incrementing = false;

    protected $table = 'entity_test_subjects';

    protected $keyType = 'string';

    protected $fillable = ['name'];
}
