# Upgrade Guide

## Upgrading from 1.x to 2.0

### `withAggregateId()` on the `DomainEvent` contract

**Who is affected:** classes that implement `Lava83\LaravelDdd\Domain\Contracts\DomainEvent` directly. Events extending the base class `Lava83\LaravelDdd\Domain\Events\DomainEvent` already have the method and need no change.

The contract now declares:

```php
public function withAggregateId(Id $aggregateId): static;
```

`Aggregate::idFromPersistence()` calls it on every buffered event recorded against the previous id. Events recorded before the first save of an aggregate whose key is assigned on insert (an `Integer` identity, or a key the API returns) are therefore dispatched with the persisted id. In 1.x this only worked for events extending the base class.

Either extend the base class, or implement the method so that it returns a copy carrying the new id and keeps payload, name, version and `occurredOn` unchanged:

```php
use Carbon\CarbonImmutable;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

final readonly class CounterCreated implements DomainEvent
{
    private CarbonImmutable $occurredOn;

    public function __construct(
        private Id $aggregateId,
        ?CarbonImmutable $occurredOn = null,
    ) {
        $this->occurredOn = $occurredOn ?? CarbonImmutable::now();
    }

    public function withAggregateId(Id $aggregateId): static
    {
        return new self($aggregateId, $this->occurredOn);
    }

    // aggregateId(), eventName(), occurredOn(), eventData(), eventVersion(), toArray() …
}
```
