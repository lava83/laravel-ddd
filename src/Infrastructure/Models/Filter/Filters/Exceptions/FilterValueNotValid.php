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
        }

        return new self("The filter value \"{$value}\" is not valid.");
    }
}
