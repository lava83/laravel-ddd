<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions;

use LogicException;

/**
 * A page item holds NULL in an order column. Laravel's cursor pagination
 * compares the cursor value with `>` / `<`, which never matches NULL, so the
 * next page would skip or repeat rows. Sort only by columns that never hold
 * NULL.
 */
class CursorValueIsNull extends LogicException
{
    public static function forParameter(string $parameter): self
    {
        return new self(
            "The cursor parameter \"{$parameter}\" is NULL for an item of the page. "
            .'Cursor pagination cannot page over NULL: order only by columns that never hold NULL.',
        );
    }
}
