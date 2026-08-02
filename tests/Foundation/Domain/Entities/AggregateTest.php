<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\PrivateIdAggregate;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;

/**
 * Rebuild an AggregateTestSubject from persisted model state.
 *
 * Version and timestamps belong to the base Entity and are only ever set via
 * hydrate(); this routes through the real fromState()/hydrate() path so tests
 * can arrange them without breaking that rule. Owned child members are handed in
 * through a `members` attribute, standing in for a loaded relation.
 *
 * @param  array<string, mixed>  $state  Model attribute overrides.
 */
function reconstituteAggregate(array $state = []): AggregateTestSubject
{
    $model = (new AggregateTestModel)->newFromBuilder(array_merge([
        'id' => EntityTestId::generate()->value(),
        'name' => 'Stack',
        'members' => new Collection,
        'created_at' => CarbonImmutable::now(),
        'updated_at' => null,
        'version' => 1,
    ], $state));

    return AggregateTestSubject::fromState($model);
}

describe('event recording', function (): void {
    it('starts with no uncommitted events and records one on demand', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');

        expect($aggregate->hasUncommittedEvents())->toBeFalse();

        $aggregate->recordEvent(new AggregateTestRenamed($aggregate->id()));

        expect($aggregate->hasUncommittedEvents())->toBeTrue()
            ->and($aggregate->uncommittedEvents())->toHaveCount(1);
    });

    it('returns cloned events detached from the internal buffer', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');
        $aggregate->rename('Renamed');

        $internal = $aggregate->eventByType('test.aggregate.renamed');
        $events = $aggregate->uncommittedEvents();

        expect($events->first())->toBeInstanceOf(AggregateTestRenamed::class)
            ->and($events->first())->not->toBe($internal);

        // Draining the returned copy must not touch the buffer.
        $events->pop();

        expect($aggregate->hasUncommittedEvents())->toBeTrue()
            ->and($aggregate->uncommittedEvents())->toHaveCount(1);
    });

    it('empties the buffer when events are committed but keeps entity state', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');
        $aggregate->rename('Renamed');

        expect($aggregate->hasUncommittedEvents())->toBeTrue();

        $aggregate->markEventsAsCommitted();

        expect($aggregate->hasUncommittedEvents())->toBeFalse()
            ->and($aggregate->uncommittedEvents())->toHaveCount(0)
            ->and($aggregate->name())->toBe('Renamed')
            ->and($aggregate->version())->toBe(2);
    });
});

describe('updateAggregateRoot', function (): void {
    it('applies the change, bumps the version and records the auto-instantiated event', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');

        $aggregate->rename('New');

        $event = $aggregate->eventByType('test.aggregate.renamed');

        expect($aggregate->name())->toBe('New')
            ->and($aggregate->version())->toBe(2)
            ->and($event)->toBeInstanceOf(AggregateTestRenamed::class)
            ->and($event->aggregateId())->toBe($aggregate->id())
            ->and($event->eventData()->toArray())->toBe([
                'old_name' => 'Old',
                'new_name' => 'New',
            ]);
    });

    it('moves state without recording anything when given no event', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');

        $aggregate->renameSilently('New');

        expect($aggregate->name())->toBe('New')
            ->and($aggregate->version())->toBe(2)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });

    it('records a pre-built event carrying its own payload, independent of the diff', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');
        $event = new AggregateTestRenamed($aggregate->id(), collect(['reason' => 'manual']));

        $aggregate->renameWithPrebuiltEvent('New', $event);

        expect($aggregate->name())->toBe('New')
            ->and($aggregate->version())->toBe(2)
            ->and($aggregate->eventByType('test.aggregate.renamed'))->toBe($event)
            ->and($aggregate->eventByType('test.aggregate.renamed')->eventData()->toArray())
            ->toBe(['reason' => 'manual']);
    });

    it('drops even a pre-built event when nothing actually changed', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Same');
        $event = new AggregateTestRenamed($aggregate->id());

        $aggregate->renameWithPrebuiltEvent('Same', $event);

        expect($aggregate->version())->toBe(1)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });

    it('rejects an event class that does not implement DomainEvent', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');

        expect(fn () => $aggregate->renameWithInvalidEventClass('New'))
            ->toThrow(LogicException::class, 'must implement DomainEvent interface');
    });

    it('applies the change before rejecting an invalid event class — the guard fires late', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');

        try {
            $aggregate->renameWithInvalidEventClass('New');
        } catch (LogicException) {
            // updateEntity() has already mutated state by the time the guard throws.
        }

        expect($aggregate->name())->toBe('New')
            ->and($aggregate->version())->toBe(2)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });
});

describe('event querying', function (): void {
    it('finds the first event of a given type, or null', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');
        $aggregate->rename('New');

        expect($aggregate->eventByType('test.aggregate.renamed'))
            ->toBeInstanceOf(AggregateTestRenamed::class)
            ->and($aggregate->eventByType('does.not.exist'))->toBeNull();
    });

    it('summarises uncommitted events against frozen time', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');
        $aggregate->rename('New');

        expect($aggregate->eventSummary()->toArray())->toBe([[
            'event_name' => 'test.aggregate.renamed',
            'aggregate_id' => $aggregate->id()->value(),
            'occurred_on' => CarbonImmutable::now()->format(DateTimeInterface::ATOM),
        ]]);
    });

    it('counts and clears events by type without disturbing the others', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');

        $aggregate->rename('New');
        $aggregate->addMember(new EntityTestSubject(EntityTestId::generate(), 'A'));
        $aggregate->addMember(new EntityTestSubject(EntityTestId::generate(), 'B'));

        expect($aggregate->countEventsOfType('test.aggregate.member_added'))->toBe(2)
            ->and($aggregate->countEventsOfType('test.aggregate.renamed'))->toBe(1)
            ->and($aggregate->countEventsOfType('nope'))->toBe(0);

        $aggregate->clearEventsOfType('test.aggregate.member_added');

        expect($aggregate->countEventsOfType('test.aggregate.member_added'))->toBe(0)
            ->and($aggregate->countEventsOfType('test.aggregate.renamed'))->toBe(1)
            ->and($aggregate->hasUncommittedEvents())->toBeTrue();
    });
});

describe('clone and serialization drop the event buffer', function (): void {
    it('resets events on clone while preserving state', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');
        $aggregate->rename('New');

        $clone = clone $aggregate;

        expect($clone->hasUncommittedEvents())->toBeFalse()
            ->and($aggregate->hasUncommittedEvents())->toBeTrue()
            ->and($clone->name())->toBe('New')
            ->and($clone->version())->toBe(2)
            ->and($clone->id()->equals($aggregate->id()))->toBeTrue();
    });

    it('drops uncommitted events across serialize/unserialize but keeps state', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Old');
        $aggregate->rename('New');

        /** @var AggregateTestSubject $restored */
        $restored = unserialize(serialize($aggregate));

        expect($restored->hasUncommittedEvents())->toBeFalse()
            ->and($restored->name())->toBe('New')
            ->and($restored->version())->toBe(2)
            ->and($restored->id()->equals($aggregate->id()))->toBeTrue();
    });
});

describe('serialization visibility trap for a private subclass identity', function (): void {
    it('keeps protected state but leaves a private subclass id uninitialized', function (): void {
        $aggregate = new PrivateIdAggregate(EntityTestId::generate(), 'kept');

        /** @var PrivateIdAggregate $restored */
        $restored = unserialize(serialize($aggregate));

        // Aggregate::__sleep() cannot see this subclass-private property, so it is
        // omitted from the payload; protected state (name, version) survives.
        expect($restored->name())->toBe('kept')
            ->and($restored->version())->toBe(1)
            ->and((new ReflectionProperty($restored, 'id'))->isInitialized($restored))->toBeFalse();
    });

    it('throws when the dropped identity is accessed after unserialize', function (): void {
        $aggregate = new PrivateIdAggregate(EntityTestId::generate(), 'kept');

        /** @var PrivateIdAggregate $restored */
        $restored = unserialize(serialize($aggregate));

        expect(fn () => $restored->id())->toThrow(Error::class);
    });
});

describe('metadata', function (): void {
    it('extends the base metadata with aggregate-specific keys', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');
        $aggregate->rename('Renamed');

        $metadata = $aggregate->metadata();

        expect($metadata)->toHaveKeys([
            'entity_type', 'entity_id', 'age_seconds',
            'is_aggregate_root', 'has_uncommitted_events',
            'uncommitted_events_count', 'version', 'created_at', 'updated_at', 'class',
        ])
            ->and($metadata['is_aggregate_root'])->toBeTrue()
            ->and($metadata['has_uncommitted_events'])->toBeTrue()
            ->and($metadata['uncommitted_events_count'])->toBe(1)
            ->and($metadata['version'])->toBe(2)
            ->and($metadata['class'])->toBe(AggregateTestSubject::class)
            ->and($metadata['entity_type'])->toBe(AggregateTestSubject::class);
    });

    it('reports an empty buffer for a fresh aggregate', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');

        $metadata = $aggregate->metadata();

        expect($metadata['has_uncommitted_events'])->toBeFalse()
            ->and($metadata['uncommitted_events_count'])->toBe(0)
            ->and($metadata['version'])->toBe(1);
    });
});

describe('factory and reconstitution', function (): void {
    it('records a creation event from the create factory', function (): void {
        $aggregate = AggregateTestSubject::create('Stack');

        expect($aggregate->hasUncommittedEvents())->toBeTrue()
            ->and($aggregate->eventByType('test.aggregate.created'))->toBeInstanceOf(AggregateTestCreated::class)
            ->and($aggregate->version())->toBe(1)
            ->and($aggregate->name())->toBe('Stack');
    });

    it('mints a distinct identity on each create call', function (): void {
        $first = AggregateTestSubject::create('Stack');
        $second = AggregateTestSubject::create('Stack');

        expect($first->id()->equals($second->id()))->toBeFalse();
    });

    it('reconstitutes from state without recording events and hydrates version and timestamps', function (): void {
        $createdAt = CarbonImmutable::now()->subDays(3);
        $updatedAt = CarbonImmutable::now()->subDay();

        $aggregate = reconstituteAggregate([
            'name' => 'Persisted',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'version' => 6,
        ]);

        expect($aggregate->hasUncommittedEvents())->toBeFalse()
            ->and($aggregate->name())->toBe('Persisted')
            ->and($aggregate->version())->toBe(6)
            ->and($aggregate->createdAt()->getTimestamp())->toBe($createdAt->getTimestamp())
            ->and($aggregate->updatedAt()->getTimestamp())->toBe($updatedAt->getTimestamp());
    });

    it('reconstitutes owned child members handed in through model state', function (): void {
        $memberId = EntityTestId::generate();
        $members = collect([new EntityTestSubject($memberId, 'Child')]);

        $aggregate = reconstituteAggregate(['members' => $members]);

        expect($aggregate->members())->toHaveCount(1)
            ->and($aggregate->containsMember($memberId))->toBeTrue()
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });
});

describe('owned child entities (entity-relationship edge cases)', function (): void {
    it('adds a child in place and records an event but does not bump the aggregate version', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');
        $member = new EntityTestSubject(EntityTestId::generate(), 'Child');

        $aggregate->addMember($member);

        // In-place collection mutation never enters updateEntity(), so the
        // aggregate is neither dirty nor version-bumped despite the new child.
        expect($aggregate->members())->toHaveCount(1)
            ->and($aggregate->countEventsOfType('test.aggregate.member_added'))->toBe(1)
            ->and($aggregate->version())->toBe(1)
            ->and($aggregate->isDirty())->toBeFalse();
    });

    it('is idempotent — re-adding the same identity is a no-op', function (): void {
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack');
        $member = new EntityTestSubject(EntityTestId::generate(), 'Child');

        $aggregate->addMember($member);
        $aggregate->addMember($member);

        expect($aggregate->members())->toHaveCount(1)
            ->and($aggregate->countEventsOfType('test.aggregate.member_added'))->toBe(1);
    });

    it('recognises membership by identity, not by other child state', function (): void {
        $memberId = EntityTestId::generate();
        $aggregate = new AggregateTestSubject(
            EntityTestId::generate(),
            'Stack',
            collect([new EntityTestSubject($memberId, 'Original')]),
        );

        $sameIdentity = new EntityTestSubject(EntityTestId::fromString($memberId->value()), 'Renamed');
        $otherIdentity = new EntityTestSubject(EntityTestId::generate(), 'Original');

        expect($aggregate->containsMember($sameIdentity->id()))->toBeTrue()
            ->and($aggregate->doesntContainMember($otherIdentity->id()))->toBeTrue();

        $aggregate->addMember($sameIdentity);

        expect($aggregate->members())->toHaveCount(1)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });

    it('detects a child-collection change only when its cardinality changes', function (): void {
        $member = new EntityTestSubject(EntityTestId::generate(), 'One');
        $aggregate = new AggregateTestSubject(EntityTestId::generate(), 'Stack', collect([$member]));

        $aggregate->syncMembers(collect([$member, new EntityTestSubject(EntityTestId::generate(), 'Two')]));

        expect($aggregate->members())->toHaveCount(2)
            ->and($aggregate->version())->toBe(2)
            ->and($aggregate->countEventsOfType('test.aggregate.members_synced'))->toBe(1);
    });

    it('cannot see a same-cardinality member swap, because a Collection is compared by its JSON cast', function (): void {
        $aggregate = new AggregateTestSubject(
            EntityTestId::generate(),
            'Stack',
            collect([new EntityTestSubject(EntityTestId::generate(), 'One')]),
        );

        // A different member, same count. These children expose no public/JSON
        // state, so both collections stringify to `[{}]` and hasChanged() (the
        // Stringable branch) sees no difference.
        $aggregate->syncMembers(collect([new EntityTestSubject(EntityTestId::generate(), 'Replacement')]));

        expect($aggregate->version())->toBe(1)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });

    it('cannot see an in-place mutation re-submitted as the same collection instance', function (): void {
        $aggregate = new AggregateTestSubject(
            EntityTestId::generate(),
            'Stack',
            collect([new EntityTestSubject(EntityTestId::generate(), 'One')]),
        );

        $aggregate->members()->push(new EntityTestSubject(EntityTestId::generate(), 'Two'));
        $aggregate->syncMembers($aggregate->members());

        // The push landed, but current and new are the same instance, so the
        // JSON casts match and no change is detected.
        expect($aggregate->members())->toHaveCount(2)
            ->and($aggregate->version())->toBe(1)
            ->and($aggregate->hasUncommittedEvents())->toBeFalse();
    });
});
