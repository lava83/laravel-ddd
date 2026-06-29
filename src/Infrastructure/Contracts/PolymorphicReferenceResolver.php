<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Contracts;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\PolymorphicReference;

interface PolymorphicReferenceResolver
{
    /**
     * @param  callable(int|string): Id  $idFactory
     */
    public function register(string $alias, callable $idFactory): void;

    public function fromPersistence(string $alias, int|string $rawId): PolymorphicReference;

    /**
     * @return array{type: string, id: string}
     */
    public function toPersistence(PolymorphicReference $reference): array;
}
