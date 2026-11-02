<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use JsonException;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidFilters;
use LogicException;

/**
 * The client filters of a list: `filters`, as a JSON string or as a
 * bracket-notation query array, read with {@see ProvidesFilters::filters()}.
 * JSON keeps `int` and `bool`. Bracket notation sends every value as a
 * string, which `$gt`, `$gte`, `$lt`, `$lte` and `$null` reject.
 *
 * The JSON is decoded before the rules run ({@see self::validationData()}),
 * so rules of the request on `filters.*` apply to both forms. Where both
 * forms meet, a JSON string in the body and an array in the query or the
 * other way round, the JSON string wins.
 *
 * @phpstan-require-extends FormRequest
 */
trait AcceptsFilters
{
    use ProvidesFilters;

    private bool $filtersDecodedForValidation = false;

    /**
     * @return array<string, list<mixed>>
     */
    protected function filterRules(): array
    {
        return [
            /**
             * The filters of the list, as a JSON list (`[{"type":"$eq","target":"status_id","value":2}]`) or in bracket
             * notation (`filters[0][type]=$eq&…`). JSON keeps numbers and booleans. Bracket notation sends every value
             * as a string, which `$gt`, `$gte`, `$lt`, `$lte` and `$null` reject.
             */
            'filters' => ['bail', new ValidFilters, 'array'],
            'filters.*' => ['required', 'array'],
        ];
    }

    /**
     * The input the rules see, with `filters` decoded. A request that
     * overrides `validationData()` itself must pass its data through
     * {@see self::withDecodedFilters()}.
     *
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        return $this->withDecodedFilters(parent::validationData());
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function withDecodedFilters(array $data): array
    {
        $this->filtersDecodedForValidation = true;

        $filters = $this->filtersInput();

        if ($filters !== null) {
            $data['filters'] = $filters;
        }

        return $data;
    }

    /**
     * `filters` as the client sent it, a JSON list decoded. A JSON string that
     * does not decode to a list stays a string, for {@see ValidFilters} to
     * reject. `input()` prefers the body over the query whatever their form;
     * here a non-blank string wins over an array.
     */
    private function filtersInput(): mixed
    {
        $body = $this->getInputSource()->all()['filters'] ?? null;
        $query = $this->query->all()['filters'] ?? null;

        $value = match (true) {
            is_string($body) && trim($body) !== '' => $body,
            is_string($query) && trim($query) !== '' => $query,
            default => $body ?? $query,
        };

        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        try {
            // Decoded twice: as objects to tell a JSON list from a JSON object, which decode to the same PHP array.
            if (! is_array(json_decode($value, false, flags: JSON_THROW_ON_ERROR))) {
                return $value;
            }

            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws LogicException the request validated `filters` without decoding them
     */
    private function clientFilterRows(): array
    {
        if (! $this->filtersDecodedForValidation) {
            throw new LogicException(
                static::class.' validated `filters` without decoding them, so its rules on `filters.*` did not see JSON filters. '
                .'If it overrides validationData(), return $this->withDecodedFilters(...) from it.',
            );
        }

        $filters = $this->filtersInput();

        /** @var array<int, array<string, mixed>> */
        return is_array($filters) ? $filters : [];
    }
}
