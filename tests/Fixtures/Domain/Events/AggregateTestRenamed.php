<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Lava83\LaravelDdd\Domain\Events\DomainEvent;

/**
 * Auto-instantiated by updateAggregateRoot() when a scalar change is applied,
 * or supplied directly to exercise the pre-built-event path.
 */
final readonly class AggregateTestRenamed extends DomainEvent
{
    public function eventName(): string
    {
        return 'test.aggregate.renamed';
    }
}
