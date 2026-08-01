<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Entity whose `status` lives in the class body, not the constructor — used to
 * prove Entity::applyChanges() silently skips non-promoted properties.
 *
 * @extends Entity<EntityTestModel, EntityTestId>
 */
final class BodyPropertyEntity extends Entity
{
    protected string $status = 'active';

    public function __construct(
        private readonly EntityTestId $id,
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

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function changeStatus(string $status): Collection
    {
        return $this->updateEntity(['status' => $status]);
    }
}
