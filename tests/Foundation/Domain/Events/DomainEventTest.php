<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent as DomainEventContract;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;

beforeEach(function (): void {
    $this->travelTo('2026-03-18 10:30:00');
    $this->id = EntityTestId::generate();
});

describe('construction', function (): void {
    it('keeps the aggregate id', function (): void {
        $event = new AggregateTestCreated($this->id);

        expect($event->aggregateId())->toBe($this->id);
    });

    it('has no payload and version one by default', function (): void {
        $event = new AggregateTestCreated($this->id);

        expect($event->eventData()->all())->toBe([])
            ->and($event->eventVersion())->toBe(1);
    });

    it('keeps payload and version when they are given', function (): void {
        $event = new AggregateTestCreated($this->id, new Collection(['name' => 'Anna']), 3);

        expect($event->eventData()->all())->toBe(['name' => 'Anna'])
            ->and($event->eventVersion())->toBe(3);
    });

    it('records when it occurred, at the time it was created', function (): void {
        $event = new AggregateTestCreated($this->id);

        $this->travel(5)->minutes();

        expect($event->occurredOn())->toBeInstanceOf(CarbonImmutable::class)
            ->and($event->occurredOn()->toDateTimeString())->toBe('2026-03-18 10:30:00');
    });

    it('fulfils the domain event contract', function (): void {
        expect(new AggregateTestCreated($this->id))->toBeInstanceOf(DomainEventContract::class);
    });

    it('takes its name from the subclass', function (): void {
        expect((new AggregateTestCreated($this->id))->eventName())->toBe('test.aggregate.created');
    });
});

describe('toArray', function (): void {
    it('describes the event', function (): void {
        $event = new AggregateTestCreated($this->id, new Collection(['name' => 'Anna']), 2);

        $array = $event->toArray();

        expect(array_keys($array))->toBe(['event_name', 'aggregate_id', 'event_data', 'event_version', 'occurred_on'])
            ->and($array['event_name'])->toBe('test.aggregate.created')
            ->and($array['event_data'])->toBe(['name' => 'Anna'])
            ->and($array['event_version'])->toBe(2)
            ->and($array['occurred_on'])->toBe('2026-03-18T10:30:00+00:00');
    });

    it('carries the aggregate id in a form that serialises like the id itself', function (): void {
        $array = (new AggregateTestCreated($this->id))->toArray();

        expect(json_encode($array['aggregate_id']))->toBe(json_encode($this->id));
    });
});

// Known defect found while characterising DomainEvent. It describes the
// behaviour we want, not the one we have; turn it into a real test when it is
// fixed.
describe('known defects', function (): void {
    it('does not let the payload change after the event was created', function (): void {
        $payload = new Collection(['name' => 'Anna']);
        $event = new AggregateTestCreated($this->id, $payload);

        $payload->put('name', 'Ben');
        $event->eventData()->put('extra', true);

        expect($event->eventData()->all())->toBe(['name' => 'Anna']);
    })->todo('the event shares its Collection with the caller and hands it out, so both can change a "readonly" event');
});
