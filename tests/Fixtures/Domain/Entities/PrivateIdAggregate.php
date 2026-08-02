<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Aggregate that declares its identity `private` — the plain-Entity convention
 * (see EntityTestSubject), which becomes a trap on an Aggregate.
 *
 * Aggregate::__sleep() derives its allow-list from get_object_vars($this) in
 * Aggregate scope, where a property declared `private` in this subclass is not
 * visible. Serialization therefore silently omits `id`, and unserialize() leaves
 * it uninitialized. AggregateTestSubject sidesteps this by declaring `id`
 * protected.
 *
 * @extends Aggregate<AggregateTestModel, EntityTestId>
 */
final class PrivateIdAggregate extends Aggregate
{
    public function __construct(
        private readonly EntityTestId $id,
        protected string $name = 'private',
    ) {
        parent::__construct();
    }

    public static function fromState(Model $state): static
    {
        return new self(EntityTestId::fromString((string) $state->getAttribute('id')));
    }

    public function id(): EntityTestId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
