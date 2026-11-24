<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions;

use LogicException;

/**
 * `Sort::apply()` was called for a sort whose order holds a field that may be
 * NULL. Laravel's `cursorPaginate()` after it compares with `>` and `<` and
 * would skip or repeat rows, so only `Sort::paginate()` orders by such a field.
 */
class SortNotApplicable extends LogicException
{
    public static function nullableField(string $field): self
    {
        return new self("The sort by \"{$field}\" may be NULL: page it with Sort::paginate(), not with apply() and cursorPaginate().");
    }
}
