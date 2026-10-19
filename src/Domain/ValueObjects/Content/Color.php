<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Content;

use Illuminate\Support\Stringable;
use Lava83\LaravelDdd\Domain\ValueObjects\Content\Enums\ColorEnum;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Color extends ValueObject
{
    private readonly Stringable $value;

    final public function __construct(string $color)
    {
        $this->value = str($color)->lower()->trim();

        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function fromString(string $color): static
    {
        return new static($color);
    }

    public function jsonSerialize(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    protected function validate(): array
    {
        if (ColorEnum::tryFrom((string) $this->value) === null) {
            return ['value' => ['Unsupported color: '.$this->value]];
        }

        return [];
    }
}
