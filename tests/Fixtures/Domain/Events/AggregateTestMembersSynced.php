<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Lava83\LaravelDdd\Domain\Events\DomainEvent;

/**
 * Auto-instantiated when the whole child collection is routed through
 * updateAggregateRoot() — used to expose how change detection treats a
 * Collection property.
 */
final readonly class AggregateTestMembersSynced extends DomainEvent
{
    public function eventName(): string
    {
        return 'test.aggregate.members_synced';
    }
}
