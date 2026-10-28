<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Lava83\LaravelDdd\Domain\Events\DomainEvent;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;

/**
 * Recorded by CounterTestSubject::create() while the identity is still the
 * placeholder 0 — the database assigns the real key only on insert.
 *
 * @extends DomainEvent<IntegerTestId>
 */
final readonly class CounterTestCreated extends DomainEvent
{
    public function eventName(): string
    {
        return 'test.counter.created';
    }
}
