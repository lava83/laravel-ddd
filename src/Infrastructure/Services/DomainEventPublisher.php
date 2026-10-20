<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;

final readonly class DomainEventPublisher
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    /**
     * @param  Collection<int, DomainEvent<Id>>  $events
     */
    public function publishEvents(Collection $events): void
    {
        $events->each(fn (DomainEvent $event) => $this->publishEvent($event));
    }

    /**
     * @param  DomainEvent<Id>  $event
     */
    public function publishEvent(DomainEvent $event): void
    {
        DB::afterCommit(fn (): ?array => $this->dispatcher->dispatch($event));
    }
}
