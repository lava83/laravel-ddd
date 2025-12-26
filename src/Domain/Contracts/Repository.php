<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Contracts;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

interface Repository
{
    /**
     * Get next available ID for this entity type
     */
    public function nextId(): Uuid;

    /**
     * Check if an aggregate exists by ID
     */
    public function exists(Uuid $id): bool;

    /**
     * Delete an aggregate by ID
     */
    public function delete(Uuid $id): void;

    /**
     * Get all aggregates
     *
     * @return Collection<int, Aggregate>
     */
    public function all(): Collection;

    public function count(): int;
}
