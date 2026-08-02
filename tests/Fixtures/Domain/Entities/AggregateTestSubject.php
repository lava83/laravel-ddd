<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestMemberAdded;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestMembersSynced;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;
use ReflectionException;
use stdClass;

/**
 * Aggregate-root fixture modelled on waz-api's AssignmentStack: a root that
 * owns a Collection of child entities (EntityTestSubject) and records domain
 * events on state change.
 *
 * `id` is protected (not private) to mirror AssignmentStack and to keep the
 * property visible to Aggregate::__sleep(), so serialization round-trips the
 * identity while still dropping the event buffer.
 *
 * @extends Aggregate<AggregateTestModel, EntityTestId>
 */
final class AggregateTestSubject extends Aggregate
{
    /**
     * @param  Collection<int, EntityTestSubject>  $members
     */
    public function __construct(
        protected readonly EntityTestId $id,
        protected string $name,
        protected Collection $members = new Collection,
    ) {
        parent::__construct();
    }

    /**
     * Brand-new aggregate: mints its own identity and records a creation event,
     * the fixture-side equivalent of AssignmentStack::create().
     */
    public static function create(string $name): self
    {
        $aggregate = new self(
            id: EntityTestId::generate(),
            name: $name,
        );

        $aggregate->recordEvent(new AggregateTestCreated(
            $aggregate->id(),
            collect(['name' => $name]),
        ));

        return $aggregate;
    }

    /**
     * Reconstitute from persisted state. Records no events — reconstitution is
     * not a domain change. With no database in the harness, pre-built child
     * members are handed in through a `members` model attribute, standing in for
     * the loaded relation AssignmentStack::fromState() maps over.
     */
    public static function fromState(Model $state): static
    {
        /** @var Collection<int, EntityTestSubject> $members */
        $members = $state->getAttribute('members') ?? new Collection;

        $aggregate = new self(
            EntityTestId::fromString((string) $state->getAttribute('id')),
            (string) $state->getAttribute('name'),
            $members,
        );

        $aggregate->hydrate($state);

        return $aggregate;
    }

    public function id(): EntityTestId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, EntityTestSubject>
     */
    public function members(): Collection
    {
        return $this->members;
    }

    public function containsMember(EntityTestId $memberId): bool
    {
        return $this->members->contains(
            fn (EntityTestSubject $member): bool => $member->id()->equals($memberId),
        );
    }

    public function doesntContainMember(EntityTestId $memberId): bool
    {
        return $this->containsMember($memberId) === false;
    }

    /**
     * Scalar change routed through the aggregate helper with an auto-instantiated
     * event: bumps the version and records AggregateTestRenamed.
     *
     * @throws ReflectionException
     */
    public function rename(string $name): void
    {
        $this->updateAggregateRoot(['name' => $name], AggregateTestRenamed::class);
    }

    /**
     * Same change, but neither an event class nor an event instance: state moves,
     * nothing is recorded.
     *
     * @throws ReflectionException
     */
    public function renameSilently(string $name): void
    {
        $this->updateAggregateRoot(['name' => $name]);
    }

    /**
     * Same change, carrying a pre-built event whose payload is independent of the
     * dirty diff (third argument to updateAggregateRoot()).
     *
     * @throws ReflectionException
     */
    public function renameWithPrebuiltEvent(string $name, DomainEvent $event): void
    {
        $this->updateAggregateRoot(['name' => $name], null, $event);
    }

    /**
     * Feeds a class-string that does not implement DomainEvent, to exercise the
     * guard inside updateAggregateRoot().
     *
     * @throws ReflectionException
     */
    public function renameWithInvalidEventClass(string $name): void
    {
        /** @phpstan-ignore argument.type */
        $this->updateAggregateRoot(['name' => $name], stdClass::class);
    }

    /**
     * Child mutation done AssignmentStack-style: mutate the collection in place
     * and record the event by hand. Deliberately does NOT go through
     * updateAggregateRoot(), so the aggregate version is untouched.
     */
    public function addMember(EntityTestSubject $member): void
    {
        if ($this->doesntContainMember($member->id())) {
            $this->members->push($member);

            $this->recordEvent(new AggregateTestMemberAdded(
                $this->id(),
                collect(['memberId' => $member->id()->value()]),
            ));
        }
    }

    /**
     * Routes the whole child collection through the change-tracking helper — the
     * trap the AssignmentStack pattern avoids. Collection is Stringable, so
     * Entity::hasChanged() compares the two collections by their JSON cast.
     *
     * @param  Collection<int, EntityTestSubject>  $members
     *
     * @throws ReflectionException
     */
    public function syncMembers(Collection $members): void
    {
        $this->updateAggregateRoot(['members' => $members], AggregateTestMembersSynced::class);
    }
}
