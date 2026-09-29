<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions;

use Exception;

class CustomFilterNotValid extends Exception
{
    public static function reservedType(string $type): self
    {
        return new self("The custom filter type \"{$type}\" collides with a built-in filter type.");
    }
}
