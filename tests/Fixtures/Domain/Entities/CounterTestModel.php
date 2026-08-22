<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Auto-incrementing, integer-keyed model backing CounterTestSubject.
 *
 * Keeps Eloquent's default key config (incrementing `int` primary key), so the
 * database owns the key and Repository::handleAutoIncrementFields() has an
 * `int` key type to null out before insert.
 *
 * @extends Model<CounterTestSubject>
 */
final class CounterTestModel extends Model
{
    protected $table = 'counter_test_subjects';

    protected $fillable = ['name'];
}
