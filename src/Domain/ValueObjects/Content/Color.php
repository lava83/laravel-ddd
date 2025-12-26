<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Content;

use Illuminate\Support\Stringable;
use Illuminate\Validation\Rule;
use Lava83\LaravelDdd\Domain\ValueObjects\Content\Enums\ColorEnum;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Color extends ValueObject
{
    private readonly Stringable $value;

    final public function __construct(string $color)
    {
        $color = str($color)->lower()->trim();
        $this->validate($color);
        $this->value = $color;
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

    private function validate(Stringable $color): void
    {
        validator()
            ->make(['color' => $color], [
                'color' => [
                    'required',
                    Rule::enum(ColorEnum::class),
                ],
            ])
            ->validate();
    }
}
