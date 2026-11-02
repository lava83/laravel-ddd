<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Each field of a sort once, whatever its direction: `email` and `-email` are
 * the same field. Fields that are not strings are left to the `sort.*` rule.
 */
final readonly class DistinctSortFields implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $columns = [];

        foreach ($value as $field) {
            if (! is_string($field)) {
                continue;
            }

            $column = str_starts_with($field, '-') ? substr($field, 1) : $field;

            if (in_array($column, $columns, true)) {
                $fail("The :attribute holds \"{$column}\" more than once.");

                return;
            }

            $columns[] = $column;
        }
    }
}
