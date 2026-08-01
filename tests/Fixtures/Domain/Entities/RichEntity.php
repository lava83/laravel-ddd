<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Entity carrying differently-typed promoted properties to drive every branch
 * of Entity::hasChanged(): CarbonImmutable, BackedEnum, Stringable, Entity.
 *
 * @extends Entity<EntityTestModel, EntityTestId>
 */
final class RichEntity extends Entity
{
    public function __construct(
        private readonly EntityTestId $id,
        protected EntityTestStatus $status = EntityTestStatus::Active,
        protected CarbonImmutable $moment = new CarbonImmutable,
        protected ?EntityTestId $label = null,
        protected ?EntityTestSubject $related = null,
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

    public function status(): EntityTestStatus
    {
        return $this->status;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function changeStatus(EntityTestStatus $status): Collection
    {
        return $this->updateEntity(['status' => $status]);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function changeMoment(CarbonImmutable $moment): Collection
    {
        return $this->updateEntity(['moment' => $moment]);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function changeLabel(EntityTestId $label): Collection
    {
        return $this->updateEntity(['label' => $label]);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function changeRelated(EntityTestSubject $related): Collection
    {
        return $this->updateEntity(['related' => $related]);
    }
}
