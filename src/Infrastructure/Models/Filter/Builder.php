<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter;

use Countable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Filter;

final readonly class Builder implements Countable
{
    /**
     * @param Collection<int, Filter> $filters
     */
    public function __construct(
        private Collection $filters = new Collection(),
    ) {}

    /**
     * @throws FilterValueNotValid
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

    /**
     * @return array<int, array{type: string, target: string, value: array<int, string>|string}>
     */
    public function toArray(): array
    {
        /** @var array<int, array{type: string, target: string, value: array<int, string>|string}> $result */
        $result = $this->filters->map(fn(Filter $filter): array => $filter->toArray())->toArray();

        return $result;
    }
}
