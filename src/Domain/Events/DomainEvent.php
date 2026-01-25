<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Events;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent as DomainEventContract;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\MongoObjectId;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

abstract class DomainEvent implements DomainEventContract
{
    private CarbonImmutable $occurredOn;

    /**
     * @param  Collection<string, mixed>  $eventData
     */
    final public function __construct(
        /** @todo here we expect only an Id not the types of it */
        private readonly Uuid|MongoObjectId  $aggregateId,
        private readonly Collection $eventData = new Collection(),
        private readonly int $eventVersion = 1,
    ) {
        $this->occurredOn = CarbonImmutable::now();
    }

    public function aggregateId(): MongoObjectId|Uuid
    {
        return $this->aggregateId;
    }

    public function occurredOn(): CarbonImmutable
    {
        return $this->occurredOn;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function eventData(): Collection
    {
        return $this->eventData;
    }

    public function eventVersion(): int
    {
        return $this->eventVersion;
    }

    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName(),
            'aggregate_id' => $this->aggregateId,
            'event_data' => $this->eventData->toArray(),
            'event_version' => $this->eventVersion,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
