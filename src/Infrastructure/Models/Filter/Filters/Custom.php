<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts\FilterDefinition;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

/**
 * An `indexzer0/eloquent-filtering` custom filter (`Filter::custom('$name')`).
 * The type is a free string and the target is optional.
 */
final class Custom implements FilterDefinition
{
    /**
     * @var Collection<array-key, mixed>|array<array-key, mixed>|string|int|float|bool
     */
    private readonly Collection|array|string|int|float|bool $value;

    /**
     * @throws FilterValueNotValid when the value is not a scalar or a (nested) array/Collection of scalars
     */
    public function __construct(
        private readonly string $type,
        mixed $value = true,
        private readonly ?string $target = null,
    ) {
        if (! self::isValidValue($value)) {
            throw FilterValueNotValid::make($value);
        }

        /** @var Collection<array-key, mixed>|array<array-key, mixed>|string|int|float|bool $value */
        $this->value = $value;
    }

    public function identifier(): string
    {
        return $this->type;
    }

    public function target(): ?string
    {
        return $this->target;
    }

    /**
     * @return Collection<array-key, mixed>|array<array-key, mixed>|string|int|float|bool
     */
    public function value(): Collection|array|string|int|float|bool
    {
        return $this->value;
    }

    /**
     * Whether the value is a scalar, or a (nested) array/Collection of scalars.
     */
    public static function isValidValue(mixed $value): bool
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isValidValue($item)) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value);
    }

    /**
     * @return array{
     *     type: string,
     *     target?: string,
     *     value: array<array-key, mixed>|string|int|float|bool
     * }
     */
    public function toArray(): array
    {
        $row = ['type' => $this->type];

        if ($this->target !== null) {
            $row['target'] = $this->target;
        }

        $row['value'] = self::normalise($this->value);

        return $row;
    }

    /**
     * @return array<array-key, mixed>|string|int|float|bool
     */
    private static function normalise(mixed $value): array|string|int|float|bool
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            return array_map(self::normalise(...), $value);
        }

        /** @var string|int|float|bool $value */
        return $value;
    }
}
