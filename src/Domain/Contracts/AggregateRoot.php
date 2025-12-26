<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use Illuminate\Support\Collection;

interface AggregateRoot
{
    /**
     * Get all uncommitted domain events
     *
     * @return Collection<int, DomainEvent>
     */
    public function uncommittedEvents(): Collection;

    /**
     * Mark all events as committed (usually after persistence)
     */
    public function markEventsAsCommitted(): void;

    /**
     * Check if there are uncommitted events
     */
    public function hasUncommittedEvents(): bool;

    /**
     * Record a new domain event
     */
    public function recordEvent(DomainEvent $event): void;
}
