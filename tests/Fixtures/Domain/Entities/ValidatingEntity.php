<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Entity with a real invariant, to exercise the constructor validation gate.
 *
 * @extends Entity<EntityTestModel, EntityTestId>
 */
final class ValidatingEntity extends Entity
{
    public function __construct(
        private readonly EntityTestId $id,
        protected string $name,
    ) {
        parent::__construct();
    }

    public static function fromState(Model $state): static
    {
        return new self(
            EntityTestId::fromString((string) $state->getAttribute('id')),
            (string) $state->getAttribute('name'),
        );
    }

    public function id(): EntityTestId
    {
        return $this->id;
    }

    /**
     * @return array<string>
     */
    public function validate(): array
    {
        if (trim($this->name) === '') {
            return ['Name is required'];
        }

        return [];
    }
}
