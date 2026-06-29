<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

final class PolymorphicReference extends ValueObject
{
    private function __construct(
        private readonly string $alias,
        private readonly Id $id,
    ) {}

    public static function of(string $alias, Id $id): self
    {
        return new self($alias, $id);
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function id(): Id
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->alias === $other->alias
            && $this->id->equals($other->id);
    }

    public function __toString(): string
    {
        return $this->alias.':'.$this->id;
    }

    /**
     * @return array{type: string, id: int|string}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->alias,
            'id' => $this->id->jsonSerialize(),
        ];
    }
}
