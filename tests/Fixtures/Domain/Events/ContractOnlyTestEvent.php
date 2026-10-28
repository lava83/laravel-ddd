<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Events;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;

/**
 * Implements only the DomainEvent contract, not the base class, so it proves
 * that Aggregate::idFromPersistence() rebinds through the contract.
 *
 * @implements DomainEvent<IntegerTestId>
 */
final readonly class ContractOnlyTestEvent implements DomainEvent
{
    private CarbonImmutable $occurredOn;

    public function __construct(
        private Id $aggregateId,
        ?CarbonImmutable $occurredOn = null,
    ) {
        $this->occurredOn = $occurredOn ?? CarbonImmutable::now();
    }

    public function aggregateId(): Id
    {
        return $this->aggregateId;
    }

    public function withAggregateId(Id $aggregateId): static
    {
        return new self($aggregateId, $this->occurredOn);
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
