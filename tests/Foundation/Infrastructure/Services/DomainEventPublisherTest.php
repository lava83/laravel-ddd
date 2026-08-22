<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Infrastructure\Services\DomainEventPublisher;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestMemberAdded;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;

/**
 * A real Laravel dispatcher paired with a buffer that captures every dispatched
 * event, in order. Exercises the actual Illuminate\Events\Dispatcher arm of the
 * constructor union rather than a mock, so what we assert is real dispatch.
 *
 * @return array{0: Dispatcher, 1: Collection<int, DomainEvent>}
 */
function recordingDispatcher(): array
{
    /** @var Collection<int, DomainEvent> $received */
    $received = new Collection;

    $dispatcher = new Dispatcher;
    $dispatcher->listen('*', function (string $eventName, array $payload) use ($received): void {
        $received->push($payload[0]);
    });

    return [$dispatcher, $received];
}

describe('DomainEventPublisher', function (): void {
    describe('publishEvent', function (): void {
        it('dispatches the event through the dispatcher', function (): void {
            [$dispatcher, $received] = recordingDispatcher();
            $event = new AggregateTestCreated(EntityTestId::generate());

            new DomainEventPublisher($dispatcher)->publishEvent($event);

            expect($received->all())->toBe([$event]);
        });
    });

    describe('publishEvents', function (): void {
        it('dispatches every event in the collection, in order', function (): void {
            [$dispatcher, $received] = recordingDispatcher();
            $id = EntityTestId::generate();
            $created = new AggregateTestCreated($id);
            $memberAdded = new AggregateTestMemberAdded($id);
            $renamed = new AggregateTestRenamed($id);

            new DomainEventPublisher($dispatcher)->publishEvents(
                collect([$created, $memberAdded, $renamed]),
            );

            expect($received->all())->toBe([$created, $memberAdded, $renamed]);
        });

        it('dispatches once per element, even when the same event repeats', function (): void {
            [$dispatcher, $received] = recordingDispatcher();
            $event = new AggregateTestCreated(EntityTestId::generate());

            (new DomainEventPublisher($dispatcher))->publishEvents(collect([$event, $event]));

            expect($received->all())->toBe([$event, $event]);
        });

        it('dispatches nothing for an empty collection', function (): void {
            [$dispatcher, $received] = recordingDispatcher();

            (new DomainEventPublisher($dispatcher))->publishEvents(collect());

            expect($received)->toBeEmpty();
        });
    });

    describe('injected dispatcher variants', function (): void {
        it('dispatches through an EventFake handed to the constructor', function (): void {
            $fake = Event::fake();
            $event = new AggregateTestRenamed(EntityTestId::generate());

            new DomainEventPublisher($fake)->publishEvent($event);

            Event::assertDispatched(AggregateTestRenamed::class);
        });

        it('dispatches a whole collection through an injected EventFake', function (): void {
            $fake = Event::fake();
            $id = EntityTestId::generate();
            $created = new AggregateTestCreated($id);
            $memberAdded = new AggregateTestMemberAdded($id);

            new DomainEventPublisher($fake)->publishEvents(
                collect([$created, $memberAdded, $memberAdded]),
            );

            // Every element reaches the fake — distinct types and repeats alike,
            // one dispatch per element rather than per unique event.
            Event::assertDispatched(AggregateTestCreated::class);
            Event::assertDispatchedTimes(AggregateTestCreated::class, 1);
            Event::assertDispatchedTimes(AggregateTestMemberAdded::class, 2);
        });
    });

    // The only real construction site is app(DomainEventPublisher::class) in
    // Repository::dispatchUncommittedEvents(), so autowiring the constructor is
    // what actually has to work — including under Event::fake(), where the
    // 'events' singleton is swapped for an EventFake. Every test above hands the
    // dispatcher in by hand and never exercises this path.
    describe('resolved from the container', function (): void {
        it('autowires the container dispatcher and reaches a real listener', function (): void {
            $received = new Collection;
            Event::listen(AggregateTestCreated::class, function (AggregateTestCreated $event) use ($received): void {
                $received->push($event);
            });
            $event = new AggregateTestCreated(EntityTestId::generate());

            app(DomainEventPublisher::class)->publishEvent($event);

            expect($received->all())->toBe([$event]);
        });

        it('still resolves and dispatches when events are faked', function (): void {
            Event::fake();

            app(DomainEventPublisher::class)->publishEvents(
                collect([new AggregateTestCreated(EntityTestId::generate())]),
            );

            Event::assertDispatched(AggregateTestCreated::class);
        });
    });
});
