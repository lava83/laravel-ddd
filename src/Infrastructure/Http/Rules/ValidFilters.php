<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterArrayNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

/**
 * The client filters of a list as {@see AcceptsFilters} hands them to the
 * validator: a list of filter arrays that {@see Builder::fromArray()} accepts
 * and whose values {@see Builder::toArray()} accepts.
 * A string at this point is a JSON string that did not decode to a list.
 *
 * A row that is not an array is left to the `filters.*` rule.
 */
final readonly class ValidFilters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $fail('The :attribute must be a JSON list of filters.');

            return;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $fail('The :attribute must be a list of filters.');

            return;
        }

        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                return;
            }

            $rows[] = $row;
        }

        try {
            // toArray() checks the values of the built-in filters, which fromArray() leaves to it.
            Builder::fromArray($rows)->toArray();
        } catch (FilterArrayNotValid|FilterValueNotValid $exception) {
            $fail($exception->getMessage());
        }
    }
}
