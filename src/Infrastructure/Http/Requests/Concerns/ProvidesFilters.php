<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\MergeStrategy;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterArrayNotValid;
use LogicException;

/**
 * The seam between {@see AcceptsFilters} and {@see AcceptsSearch}. Both use
 * this trait, so a request has one `filters()` whichever of the two it uses.
 * PHP imports the same trait method only once.
 *
 * @internal use {@see AcceptsFilters} and/or {@see AcceptsSearch}
 *
 * @phpstan-require-extends FormRequest
 */
trait ProvidesFilters
{
    /**
     * The filters the client asked for: the `filters` of {@see AcceptsFilters}
     * and the search phrase of {@see AcceptsSearch}, whichever the request
     * uses. Merge them behind the scope of the acting user with
     * {@see MergeStrategy::KeepExisting}: they only ever narrow it.
     *
     * @throws FilterArrayNotValid
     * @throws LogicException the request validated `filters` without decoding them, or its search type is a client filter type
     */
    public function filters(): Builder
    {
        $filters = method_exists($this, 'clientFilterRows')
            ? Builder::fromArray($this->clientFilterRows())
            : Builder::make();

        return method_exists($this, 'withSearchFilter') ? $this->withSearchFilter($filters) : $filters;
    }
}
