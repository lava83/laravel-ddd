<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

/**
 * @template TId of Id
 */
interface AggregateRoot
{
    /**
     * Get all uncommitted domain events
     *
     * @return Collection<int, DomainEvent<TId>>
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
     *
     * @param  DomainEvent<TId>  $event
     */
    public function recordEvent(DomainEvent $event): void;
}
