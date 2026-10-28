<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

/**
 * @template-covariant TId of Id
 *
 * @todo 2.0 (#47): add `withAggregateId(Id $aggregateId): static`, so that
 *       Aggregate::idFromPersistence() can rebind every buffered event, not only
 *       those extending Events\DomainEvent. Adding it in 1.x would break
 *       existing implementers of this contract.
 */
interface DomainEvent
{
    /**
     * Get the aggregate ID that triggered this event
     *
     * @return TId
     */
    public function aggregateId(): Id;

    /**
     * Get the event name/type
     */
    public function eventName(): string;

    /**
     * Get when this event occurred
     */
    public function occurredOn(): DateTimeImmutable;

    /**
     * Get the event payload/data
     *
     * @return Collection<string, mixed>
     */
    public function eventData(): Collection;

    /**
     * Get event version for serialization compatibility
     */
    public function eventVersion(): int;

    /**
     * Convert the event to an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
