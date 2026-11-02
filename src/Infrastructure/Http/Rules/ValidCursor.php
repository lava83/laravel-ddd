<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;

/**
 * A cursor of the list: it decodes, and it carries exactly the parameters of
 * the order of the list, each a scalar. A cursor issued for another sort, or a
 * forged one, fails here as a 422 instead of as an `UnexpectedValueException`
 * in the read query.
 *
 * A cursor of the same columns in another direction passes: Laravel's cursor
 * does not record the direction. The page then starts at the cursor row in the
 * new direction.
 */
final readonly class ValidCursor implements ValidationRule
{
    /**
     * @param  list<string>|null  $parameters  the parameters of the order, see {@see Sort::cursorParameters()}; null only checks that the cursor decodes
     */
    public function __construct(
        private ?array $parameters = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cursor = Cursor::fromEncoded($value);

        if ($cursor === null) {
            $fail('The :attribute is not a valid cursor.');

            return;
        }

        if ($this->parameters === null) {
            return;
        }

        $carried = $cursor->toArray();
        unset($carried['_pointsToNextItems']);

        $expected = $this->parameters;
        $names = array_keys($carried);
        sort($expected);
        sort($names);

        if ($names !== $expected) {
            $fail('The :attribute does not belong to the sort of the list.');

            return;
        }

        foreach ($carried as $carriedValue) {
            if (! is_scalar($carriedValue)) {
                $fail('The :attribute is not a valid cursor.');

                return;
            }
        }
    }
}
