<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;

/**
 * Round-trips AggregateTestSubject through the REAL base helpers
 * (findOrCreateModelFillData -> findOrCreateModel), which query the database, so
 * the repository suite exercises actual persistence rather than a hand-built
 * model. Requires a schema — the harness creates one in RepositoryTest.
 *
 * @extends EntityMapper<AggregateTestSubject, AggregateTestModel>
 */
final class AggregateTestMapper extends EntityMapper
{
    protected static ?string $modelClass = AggregateTestModel::class;

    public static function toEntity(Model $model, bool $deep = false): Entity
    {
        return AggregateTestSubject::fromState($model);
    }

    public static function toModel(Entity $entity): Model
    {
        return self::findOrCreateModelFillData($entity, [
            'name' => $entity instanceof AggregateTestSubject ? $entity->name() : '',
        ]);
    }
}
