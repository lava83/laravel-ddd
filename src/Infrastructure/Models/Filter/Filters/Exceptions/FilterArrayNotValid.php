<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions;

use Exception;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

class FilterArrayNotValid extends Exception
{
    public static function missingKey(string $key): self
    {
        return new self("The filter array is missing the required \"{$key}\" key.");
    }

    public static function unknownType(mixed $type): self
    {
        return new self(sprintf('The filter type "%s" is not a known filter type.', self::stringify($type)));
    }

    public static function invalidTarget(mixed $target): self
    {
        return new self(sprintf('The filter target must be a string, got %s.', get_debug_type($target)));
    }

    public static function valueTypeMismatch(FilterType $type, mixed $value): self
    {
        return new self(sprintf('The value for filter type "%s" has an invalid type (%s).', $type->value, get_debug_type($value)));
    }

    private static function stringify(mixed $value): string
    {
        return is_string($value) ? $value : get_debug_type($value);
    }
}
