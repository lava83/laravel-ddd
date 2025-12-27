<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions;

use Exception;

class FilterValueNotValid extends Exception
{
    public static function make(mixed $value): self
    {
        return new self("The filter value \"{$value}\" is not valid.");
    }
}
