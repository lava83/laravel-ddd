<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models;

use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;

/**
 * Extends the package model but leaves $entityClassName unset, which is the
 * mistake entityMapper() guards against.
 *
 * @extends Model<EntityTestSubject>
 */
final class ModelWithoutEntityTestModel extends Model
{
    protected $table = 'model_without_entity_items';
}
