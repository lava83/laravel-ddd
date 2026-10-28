<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

/**
 * @template-covariant TId of Id
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
     * A copy of this event for another identity of the same aggregate, e.g. the
     * key the database assigned on insert. Payload, name, version and occurrence
     * time stay unchanged. Aggregate::idFromPersistence() calls it on every
     * buffered event recorded against the previous id.
     */
    public function withAggregateId(Id $aggregateId): static;

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
