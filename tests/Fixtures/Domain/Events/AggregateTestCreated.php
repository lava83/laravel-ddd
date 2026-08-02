<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Lava83\LaravelDdd\Domain\Events\DomainEvent;

/**
 * Recorded by AggregateTestSubject::create() — proves a factory emits a
 * creation event on a brand-new aggregate.
 */
final readonly class AggregateTestCreated extends DomainEvent
{
    public function eventName(): string
    {
        return 'test.aggregate.created';
    }
}
