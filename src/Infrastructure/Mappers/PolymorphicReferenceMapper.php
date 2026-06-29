<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers;

use InvalidArgumentException;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\PolymorphicReference;
use Lava83\LaravelDdd\Infrastructure\Contracts\PolymorphicReferenceResolver;

final class PolymorphicReferenceMapper implements PolymorphicReferenceResolver
{
    /**
     * @var array<string, callable(int|string): Id>
     */
    private array $factories = [];

    public function register(string $alias, callable $idFactory): void
    {
        $this->factories[$alias] = $idFactory;
    }

    public function fromPersistence(string $alias, int|string $rawId): PolymorphicReference
    {
        if (! isset($this->factories[$alias])) {
            throw new InvalidArgumentException(
                'No id factory registered for polymorphic alias: '.$alias
            );
        }

        return PolymorphicReference::of($alias, ($this->factories[$alias])($rawId));
    }

    public function toPersistence(PolymorphicReference $reference): array
    {
        return [
            'type' => $reference->alias(),
            'id' => (string) $reference->id(),
        ];
    }
}
