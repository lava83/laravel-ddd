<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions;

use LogicException;

/**
 * A page item holds NULL in an order column that is not declared nullable.
 * Laravel's cursor pagination compares the cursor value with `>` / `<`, which
 * never matches NULL, so the next page would skip or repeat rows. Declare the
 * column nullable and page with `Sort::paginate()`, or sort only by columns
 * that never hold NULL.
 */
class CursorValueIsNull extends LogicException
{
    public static function forParameter(string $parameter): self
    {
        return new self(
            "The cursor parameter \"{$parameter}\" is NULL for an item of the page. "
            .'Laravel\'s cursor pagination cannot page over NULL: declare the column nullable and page with Sort::paginate(), '
            .'or order only by columns that never hold NULL.',
        );
    }
}
