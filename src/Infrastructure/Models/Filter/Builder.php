<?php

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter;

use Countable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\NoFilterTypeDeclared;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Filter;

final readonly class Builder implements Countable
{
    /**
     * @param Collection<Filter> $filters
     */
    public function __construct(
        private Collection $filters = new Collection(),
    )
    {
    }

    /**
     * @throws NoFilterTypeDeclared
     */
    public function eq(string $target, string $value): self
    {
        $this->filters->add(new Equal($target, $value));

        return $this;
    }

    public function count(): int
    {
        return $this->filters->count();
    }

    public function toArray(): array
    {
        return $this->filters
            ->map(fn (Filter $filter): array => $filter->toArray())
            ->toArray();
    }
}
