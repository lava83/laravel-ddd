<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

interface DomainEvent
{
    /**
     * Get the aggregate ID that triggered this event
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
