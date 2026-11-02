<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions;

use Exception;

class SortNotValid extends Exception
{
    public static function malformedField(mixed $field): self
    {
        return new self(sprintf(
            'A sort field must be a column name, "-" in front for descending, got %s.',
            is_string($field) ? '"'.$field.'"' : get_debug_type($field),
        ));
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function unknownField(string $field, array $allowed): self
    {
        return new self(sprintf('The list cannot be sorted by "%s". Sortable: %s.', $field, implode(', ', $allowed)));
    }

    public static function duplicateField(string $field): self
    {
        return new self("The list is sorted by \"{$field}\" more than once.");
    }
}
