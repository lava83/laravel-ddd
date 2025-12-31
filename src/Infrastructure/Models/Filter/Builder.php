<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter;

use Countable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\BetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Filter;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThan;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\GreaterThanEqualTo;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\In;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNotNull;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\IsNull;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThan;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\LessThanEqualTo;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Like;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetween;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotBetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotEqual;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotIn;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\NotLike;

final readonly class Builder implements Countable
{
    /**
     * @param Collection<int, Filter> $filters
     */
    public function __construct(
        private Collection $filters = new Collection(),
    ) {}

    public function eq(string $target, string|int|float $value): self
    {
        $this->filters->add(new Equal($target, $value));

        return $this;
    }

    public function neq(string $target, string|int|float $value): self
    {
        $this->filters->add(new NotEqual($target, $value));

        return $this;
    }

    /**
     * @param array<int, string|int|float> $value
     */
    public function between(string $target, array $value): self
    {
        $this->filters->add(new Between($target, $value));

        return $this;
    }

    /**
     * @param array<int, string|int|float> $value
     */
    public function notBetween(string $target, array $value): self
    {
        $this->filters->add(new NotBetween($target, $value));

        return $this;
    }

    /**
     * @param array<int, string> $value
     */
    public function betweenColumns(string $target, array $value): self
    {
        $this->filters->add(new BetweenColumns($target, $value));

        return $this;
    }

    /**
     * @param array<int, string> $value
     */
    public function notBetweenColumns(string $target, array $value): self
    {
        $this->filters->add(new NotBetweenColumns($target, $value));

        return $this;
    }

    public function gt(string $target, int|float $value): self
    {
        $this->filters->add(new GreaterThan($target, $value));

        return $this;
    }

    public function gte(string $target, int|float $value): self
    {
        $this->filters->add(new GreaterThanEqualTo($target, $value));

        return $this;
    }

    /**
     * @param array<int, string|int|float> $value
     */
    public function in(string $target, array $value): self
    {
        $this->filters->add(new In($target, $value));

        return $this;
    }

    /**
     * @param array<int, string|int|float> $value
     */
    public function notIn(string $target, array $value): self
    {
        $this->filters->add(new NotIn($target, $value));

        return $this;
    }

    public function like(string $target, string|int|float $value): self
    {
        $this->filters->add(new Like($target, $value));

        return $this;
    }

    public function notLike(string $target, string|int|float $value): self
    {
        $this->filters->add(new NotLike($target, $value));

        return $this;
    }

    public function lt(string $target, int|float $value): self
    {
        $this->filters->add(new LessThan($target, $value));

        return $this;
    }

    public function lte(string $target, int|float $value): self
    {
        $this->filters->add(new LessThanEqualTo($target, $value));

        return $this;
    }

    public function isNull(string $target): self
    {
        $this->filters->add(new IsNull($target));

        return $this;
    }

    public function isNotNull(string $target): self
    {
        $this->filters->add(new IsNotNull($target));

        return $this;
    }

    /**
     * @return Collection<int, Filter>
     */
    public function filters(): Collection
    {
        return $this->filters;
    }

    public function count(): int
    {
        return $this->filters->count();
    }

    /**
     * @return array<int, array{type: string, target: string, value: array<int, string|int|float>|string|int|float|bool}>
     */
    public function toArray(): array
    {
        /** @var array<int, array{type: string, target: string, value: array<int, string|int|float>|string|int|float|bool}> $result */
        $result = $this->filters->map(fn(Filter $filter): array => $filter->toArray())->toArray();

        return $result;
    }
}
