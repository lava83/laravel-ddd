<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Entities;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\AggregateRoot;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use LogicException;

/**
 * Base class for Aggregate Root entities
 * Extends BaseEntity and adds domain event handling
 */
abstract class Aggregate extends Entity implements AggregateRoot
{
    /**
     * @param  Collection<int, DomainEvent>  $domainEvents
     */
    public function __construct(
        private Collection $domainEvents = new Collection(),
    ) {
        parent::__construct();
    }

    /**
     * Override clone to reset events
     */
    public function __clone()
    {
        parent::__clone();
        // Reset events on clone to prevent event duplication
        // @todo make method resetEvents()
        // or similar to reset events in a more generic way
        // in case we want to clone an aggregate root with events
        // that should not be cloned
        $this->domainEvents = collect();
    }

    /**
     * Serialization control - don't serialize uncommitted events
     */
    public function __sleep(): array
    {
        $vars = array_keys(get_object_vars($this));

        return array_diff($vars, ['domainEvents']);
    }

    public function __wakeup(): void
    {
        // Reset events array after unserialization
        $this->domainEvents = collect();
    }

    /**
     * Domain Events Management
     */
    public function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents->push($event);
    }

    /**
     * @return Collection<int, DomainEvent>
     */
    public function uncommittedEvents(): Collection
    {
        // Return a copy of the events to prevent external modification
        return $this->domainEvents->map(fn (DomainEvent $event): DomainEvent => clone $event);
    }

    public function markEventsAsCommitted(): void
    {
        $this->domainEvents = collect();
    }

    public function hasUncommittedEvents(): bool
    {
        return ! $this->domainEvents->isEmpty();
    }

    /**
     * Enhanced metadata for aggregate roots
     */
    public function metadata(): array
    {
        return array_merge(parent::metadata(), [
            'is_aggregate_root' => true,
            'has_uncommitted_events' => $this->hasUncommittedEvents(),
            'uncommitted_events_count' => count($this->domainEvents),
            'version' => $this->version(),
            'created_at' => $this->createdAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt()->format(DateTimeImmutable::ATOM),
            'class' => static::class,
        ]);
    }

    /**
     * Get summary of uncommitted events for debugging
     *
     * @return Collection<int, array<string, string>>
     */
    public function eventSummary(): Collection
    {
        return $this->domainEvents->map(fn(DomainEvent $event): array => [
            'event_name' => $event->eventName(),
            'aggregate_id' => $event->aggregateId()->value(),
            'occurred_on' => $event->occurredOn()->format(DateTimeImmutable::ATOM),
        ]);
    }

    public function eventByType(string $eventName): ?DomainEvent
    {
        return $this->domainEvents->first(fn (DomainEvent $event): bool => $event->eventName() === $eventName) ?? null;
    }

    /**
     * Clear specific event types (useful for testing)
     */
    public function clearEventsOfType(string $eventName): void
    {
        $this->domainEvents = $this->domainEvents->filter(fn (DomainEvent $event): bool => $event->eventName() !== $eventName);
    }

    /**
     * Count events of specific type
     */
    public function countEventsOfType(string $eventName): int
    {
        return $this->domainEvents->filter(fn (DomainEvent $event): bool => $event->eventName() === $eventName)->count();
    }

    /**
     * Helper method for aggregate roots to update and record change event
     *
     * @param  array<string, mixed>  $changes  Key-value pairs of changes made to the aggregate
     */
    protected function updateAggregateRoot(
        array $changes,
        ?string $eventClass = null,
        ?DomainEvent $event = null
    ): void {
        $changesCollection = $this->updateEntity($changes);

        if ($changesCollection->isEmpty()) {
            return;
        }

        if ($eventClass !== null) {
            if (! is_a($eventClass, DomainEvent::class, true)) {
                throw new LogicException(sprintf('Event class %s must implement DomainEvent interface', $eventClass));
            }

            $event = new $eventClass($this->id(), $changesCollection);
        }

        if ($event instanceof DomainEvent) {
            $this->recordEvent($event);
        }
    }

    protected function updateDirtyEntity(): void
    {
        parent::updateDirtyEntity();

        throw new LogicException('updateDirtyEntity must be implemented in child classes to apply changes');
    }
}
