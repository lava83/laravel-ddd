<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Events;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent as DomainEventContract;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use ReflectionClass;
use ReflectionProperty;

/**
 * @template-covariant TId of Id
 *
 * @implements DomainEventContract<TId>
 */
abstract readonly class DomainEvent implements DomainEventContract
{
    private CarbonImmutable $occurredOn;

    /**
     * @var Collection<string, mixed>
     */
    private Collection $eventData;

    /**
     * @param  TId  $aggregateId
     * @param  Collection<string, mixed>|array<string, mixed>  $eventData
     */
    final public function __construct(
        private readonly Id $aggregateId,
        Collection|array $eventData = new Collection,
        private readonly int $eventVersion = 1,
    ) {
        $this->eventData = new Collection($eventData);
        $this->occurredOn = CarbonImmutable::now();
    }

    /**
     * @return TId
     */
    public function aggregateId(): Id
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
        return new Collection($this->eventData->all());
    }

    public function eventVersion(): int
    {
        return $this->eventVersion;
    }

    /**
     * A copy of this event for another identity of the same aggregate, e.g. the
     * key the database assigned on insert. Payload, version and occurrence time
     * are kept. The final constructor would reset occurredOn, so the copy is
     * built without it and its readonly properties are initialised by reflection.
     */
    public function withAggregateId(Id $aggregateId): static
    {
        $copy = (new ReflectionClass($this))->newInstanceWithoutConstructor();

        $state = [
            'aggregateId' => $aggregateId,
            'eventData' => new Collection($this->eventData->all()),
            'eventVersion' => $this->eventVersion,
            'occurredOn' => $this->occurredOn,
        ];

        foreach ($state as $property => $value) {
            (new ReflectionProperty(self::class, $property))->setValue($copy, $value);
        }

        return $copy;
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
