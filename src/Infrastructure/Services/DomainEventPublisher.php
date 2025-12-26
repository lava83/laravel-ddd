<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Services;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;

final readonly class DomainEventPublisher
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    /**
     * @param  Collection<int, DomainEvent>  $events
     */
    public function publishEvents(Collection $events): void
    {
        $events->each(fn(DomainEvent $event) => $this->publishEvent($event));
    }

    public function publishEvent(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
