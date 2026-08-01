<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Entity;
use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * Minimal concrete entity with a promoted, mutable `name` property.
 *
 * An extending entity declares only its own domain fields and calls
 * parent::__construct() with no arguments. created_at / updated_at / version
 * are the base Entity's concern — populated by defaults on a fresh instance and
 * by hydrate() on reconstitution — never forwarded up by the subclass.
 *
 * @extends Entity<EntityTestModel, EntityTestId>
 */
final class EntityTestSubject extends Entity
{
    public function __construct(
        private readonly EntityTestId $id,
        protected string $name,
    ) {
        parent::__construct();
    }

    /**
     * Domain factory for a brand-new entity.
     *
     * Mirrors the `create()` convention used by entities and aggregates in
     * consuming applications: generate the identity internally, accept the
     * domain fields as named arguments, and let the base populate
     * created_at / updated_at / version. Consuming apps usually call
     * `SomeId::new()`; this package's identities expose `generate()`.
     */
    public static function create(string $name): self
    {
        return new self(
            id: EntityTestId::generate(),
            name: $name,
        );
    }

    public static function fromState(Model $state): static
    {
        $entity = new self(
            EntityTestId::fromString((string) $state->getAttribute('id')),
            (string) $state->getAttribute('name'),
        );

        $entity->hydrate($state);

        return $entity;
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
     * @return Collection<string, mixed>
     */
    public function rename(string $name): Collection
    {
        return $this->updateEntity(['name' => $name]);
    }
}
