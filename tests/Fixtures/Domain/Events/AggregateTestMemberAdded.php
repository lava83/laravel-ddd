<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Lava83\LaravelDdd\Domain\Events\DomainEvent;

/**
 * Recorded by hand inside AggregateTestSubject::addMember(), mirroring how
 * AssignmentStack records AssignmentAdded outside the change-tracking helper.
 */
final readonly class AggregateTestMemberAdded extends DomainEvent
{
    public function eventName(): string
    {
        return 'test.aggregate.member_added';
    }
}
