<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions;

use Exception;

class FilterValueNotValid extends Exception
{
    public static function make(mixed $value): self
    {
        if (is_array($value)) {
            $encoded = json_encode($value);
            $value = $encoded !== false ? $encoded : 'array';
        } elseif ($value !== null && ! is_scalar($value)) {
            $value = get_debug_type($value);
        }

        return new self("The filter value \"{$value}\" is not valid.");
    }
}
