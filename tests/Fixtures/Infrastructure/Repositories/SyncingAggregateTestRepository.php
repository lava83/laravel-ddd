<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Repositories;

use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;

/**
 * Aggregate repository that exercises the syncDependencies() seam (option A):
 * it records when the hook fires and the model it received, so a test can prove
 * the hook runs during saveEntity() and *before* domain events are dispatched.
 *
 * @extends Repository<AggregateTestModel, AggregateTestSubject>
 */
final class SyncingAggregateTestRepository extends Repository
{
    /** @var class-string<AggregateTestSubject> */
    protected ?string $entityClassName = AggregateTestSubject::class;

    /** @var list<string> */
    public array $log = [];

    public ?AggregateTestModel $syncedModel = null;

    public function save(AggregateTestSubject $aggregate): AggregateTestModel
    {
        /** @var AggregateTestModel $model */
        $model = $this->saveEntity($aggregate);

        return $model;
    }

    /**
     * @param  AggregateTestSubject  $aggregate
     * @param  AggregateTestModel  $model
     */
    protected function syncDependencies(Aggregate $aggregate, Model $model): void
    {
        $this->log[] = 'synced';

        /** @var AggregateTestModel $model */
        $this->syncedModel = $model;
    }
}
