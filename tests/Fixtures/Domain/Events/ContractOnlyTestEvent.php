<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;

/**
 * Implements only the DomainEvent contract, not the base class, so it has no
 * withAggregateId() and Aggregate::idFromPersistence() must leave it as it is.
 *
 * @implements DomainEvent<IntegerTestId>
 */
final readonly class ContractOnlyTestEvent implements DomainEvent
{
    private CarbonImmutable $occurredOn;

    public function __construct(private IntegerTestId $aggregateId)
    {
        $this->occurredOn = CarbonImmutable::now();
    }

    public function aggregateId(): IntegerTestId
    {
        return $this->aggregateId;
    }

    public function eventName(): string
    {
        return 'test.contract_only';
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
        return new Collection;
    }

    public function eventVersion(): int
    {
        return 1;
    }

    public function toArray(): array
    {
        return [];
    }
}
