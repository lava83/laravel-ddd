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
 *
 * Also thrown for a nullable order column the item does not have at all: it
 * would read as NULL although the row holds a value.
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

    public static function forMissingAttribute(string $parameter, string $attribute): self
    {
        return new self(
            "The cursor parameter \"{$parameter}\" cannot be read: an item of the page has no \"{$attribute}\". "
            .'Select the column in the list query; without it, the cursor would carry NULL and skip rows.',
        );
    }
}
