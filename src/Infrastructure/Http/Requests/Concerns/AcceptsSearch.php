<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;
use LogicException;

/**
 * The search phrase of a list: `search`. A trimmed, non-blank phrase ends up
 * in {@see ProvidesFilters::filters()} as `custom(searchFilterType(), $phrase)`,
 * with or without {@see AcceptsFilters}.
 *
 * Register the search type (e.g. a {@see TokenSearchFilterMethod}) in
 * `eloquent-filtering.custom_filters` only, never in
 * `laravel-ddd.filters.custom_types`: `filters` then rejects it, so a client
 * reaches the search only through `search` and its maximum length.
 *
 * @phpstan-require-extends FormRequest
 */
trait AcceptsSearch
{
    use ProvidesFilters;

    /**
     * The custom filter type the phrase is applied as, e.g. `$b2cUserSearch`.
     */
    abstract protected function searchFilterType(): string;

    protected function maxSearchLength(): int
    {
        return 100;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function searchRules(): array
    {
        return [
            /**
             * A search phrase. Every word must match one of the searched attributes, which the description of the
             * endpoint names.
             */
            'search' => ['bail', 'nullable', 'string', 'max:'.$this->maxSearchLength()],
        ];
    }

    /**
     * The trimmed phrase, null if the client sent none or a blank one.
     */
    public function searchPhrase(): ?string
    {
        $phrase = $this->string('search')->trim()->value();

        return $phrase === '' ? null : $phrase;
    }

    /**
     * Add the phrase to the filters of {@see ProvidesFilters::filters()}.
     *
     * @throws LogicException the search type is a client filter type
     */
    private function withSearchFilter(Builder $filters): Builder
    {
        $type = $this->searchFilterType();

        $clientTypes = config('laravel-ddd.filters.custom_types', []);

        if (is_array($clientTypes) && in_array($type, $clientTypes, true)) {
            throw new LogicException(
                "The search type \"{$type}\" is in `laravel-ddd.filters.custom_types`, so a client can send it through `filters` "
                .'and skip the rules of `search`. Register it in `eloquent-filtering.custom_filters` only.',
            );
        }

        $phrase = $this->searchPhrase();

        return $phrase === null ? $filters : $filters->custom($type, $phrase);
    }
}
