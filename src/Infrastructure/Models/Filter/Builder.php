<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter;

use Countable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\MergeStrategy;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Between;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\BetweenColumns;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterArrayNotValid;
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
     * @param  Collection<int, Filter>  $filters
     */
    public function __construct(
        private Collection $filters = new Collection,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * Combine this builder with another, returning a new Builder and mutating
     * neither operand.
     *
     * {@see MergeStrategy::KeepExisting} appends the incoming filters and never
     * removes an existing one, so default (e.g. tenant-scoping) filters always
     * survive. {@see MergeStrategy::Override} lets an incoming filter replace
     * existing filters that match on both target and operator (type).
     */
    public function merge(self $incoming, MergeStrategy $strategy = MergeStrategy::KeepExisting): self
    {
        $existing = $this->filters();
        $incomingFilters = $incoming->filters();

        if ($strategy === MergeStrategy::Override) {
            $existing = $existing->reject(
                fn (Filter $filter): bool => $incomingFilters->contains(
                    fn (Filter $candidate): bool => $candidate->target() === $filter->target()
                        && $candidate->type() === $filter->type(),
                ),
            );
        }

        return new self($existing->concat($incomingFilters)->values());
    }

    /**
     * Rebuild a Builder from the array shape produced by {@see self::toArray()}.
     *
     * @param  array<int, array<string, mixed>>  $filters
     *
     * @throws FilterArrayNotValid
     */
    public static function fromArray(array $filters): self
    {
        $builder = self::make();

        foreach ($filters as $row) {
            $type = self::resolveType($row);
            $target = self::resolveTarget($row);
            $value = self::resolveValue($row);

            match ($type) {
                FilterType::Equal => $builder->eq($target, self::scalarValue($type, $value)),
                FilterType::NotEqual => $builder->neq($target, self::scalarValue($type, $value)),
                FilterType::Like => $builder->like($target, self::scalarValue($type, $value)),
                FilterType::NotLike => $builder->notLike($target, self::scalarValue($type, $value)),
                FilterType::GreaterThan => $builder->gt($target, self::numericValue($type, $value)),
                FilterType::GreaterThanEqualTo => $builder->gte($target, self::numericValue($type, $value)),
                FilterType::LessThan => $builder->lt($target, self::numericValue($type, $value)),
                FilterType::LessThanEqualTo => $builder->lte($target, self::numericValue($type, $value)),
                FilterType::Between => $builder->between($target, self::arrayValue($type, $value)),
                FilterType::NotBetween => $builder->notBetween($target, self::arrayValue($type, $value)),
                FilterType::In => $builder->in($target, self::arrayValue($type, $value)),
                FilterType::NotIn => $builder->notIn($target, self::arrayValue($type, $value)),
                FilterType::BetweenColumns => $builder->betweenColumns($target, self::stringArrayValue($type, $value)),
                FilterType::NotBetweenColumns => $builder->notBetweenColumns($target, self::stringArrayValue($type, $value)),
                // $null carries both IsNull (true) and IsNotNull (false); the value disambiguates.
                FilterType::IsNull => self::boolValue($type, $value) === false
                    ? $builder->isNotNull($target)
                    : $builder->isNull($target),
            };
        }

        return $builder;
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws FilterArrayNotValid
     */
    private static function resolveType(array $row): FilterType
    {
        if (! array_key_exists('type', $row)) {
            throw FilterArrayNotValid::missingKey('type');
        }

        $type = $row['type'];

        if (! is_string($type)) {
            throw FilterArrayNotValid::unknownType($type);
        }

        return FilterType::tryFrom($type) ?? throw FilterArrayNotValid::unknownType($type);
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws FilterArrayNotValid
     */
    private static function resolveTarget(array $row): string
    {
        if (! array_key_exists('target', $row)) {
            throw FilterArrayNotValid::missingKey('target');
        }

        $target = $row['target'];

        if (! is_string($target)) {
            throw FilterArrayNotValid::invalidTarget($target);
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws FilterArrayNotValid
     */
    private static function resolveValue(array $row): mixed
    {
        if (! array_key_exists('value', $row)) {
            throw FilterArrayNotValid::missingKey('value');
        }

        return $row['value'];
    }

    /**
     * @throws FilterArrayNotValid
     */
    private static function scalarValue(FilterType $type, mixed $value): string|int|float|bool
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw FilterArrayNotValid::valueTypeMismatch($type, $value);
        }

        return $value;
    }

    /**
     * @throws FilterArrayNotValid
     */
    private static function numericValue(FilterType $type, mixed $value): int|float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw FilterArrayNotValid::valueTypeMismatch($type, $value);
        }

        return $value;
    }

    /**
     * @return array<int, string|int|float>
     *
     * @throws FilterArrayNotValid
     */
    private static function arrayValue(FilterType $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw FilterArrayNotValid::valueTypeMismatch($type, $value);
        }

        $values = [];

        foreach ($value as $item) {
            if (! is_string($item) && ! is_int($item) && ! is_float($item)) {
                throw FilterArrayNotValid::valueTypeMismatch($type, $value);
            }

            $values[] = $item;
        }

        return $values;
    }

    /**
     * @return array<int, string>
     *
     * @throws FilterArrayNotValid
     */
    private static function stringArrayValue(FilterType $type, mixed $value): array
    {
        if (! is_array($value)) {
            throw FilterArrayNotValid::valueTypeMismatch($type, $value);
        }

        $values = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw FilterArrayNotValid::valueTypeMismatch($type, $value);
            }

            $values[] = $item;
        }

        return $values;
    }

    /**
     * @throws FilterArrayNotValid
     */
    private static function boolValue(FilterType $type, mixed $value): bool
    {
        if (! is_bool($value)) {
            throw FilterArrayNotValid::valueTypeMismatch($type, $value);
        }

        return $value;
    }

    public function eq(string $target, string|int|float|bool $value): self
    {
        $this->filters->add(new Equal($target, $value));

        return $this;
    }

    public function neq(string $target, string|int|float|bool $value): self
    {
        $this->filters->add(new NotEqual($target, $value));

        return $this;
    }

    /**
     * @param  array<int, string|int|float>  $value
     */
    public function between(string $target, array $value): self
    {
        $this->filters->add(new Between($target, $value));

        return $this;
    }

    /**
     * @param  array<int, string|int|float>  $value
     */
    public function notBetween(string $target, array $value): self
    {
        $this->filters->add(new NotBetween($target, $value));

        return $this;
    }

    /**
     * @param  array<int, string>  $value
     */
    public function betweenColumns(string $target, array $value): self
    {
        $this->filters->add(new BetweenColumns($target, $value));

        return $this;
    }

    /**
     * @param  array<int, string>  $value
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
     * @param  array<int, string|int|float>  $value
     */
    public function in(string $target, array $value): self
    {
        $this->filters->add(new In($target, $value));

        return $this;
    }

    /**
     * @param  array<int, string|int|float>  $value
     */
    public function notIn(string $target, array $value): self
    {
        $this->filters->add(new NotIn($target, $value));

        return $this;
    }

    public function like(string $target, string|int|float|bool $value): self
    {
        $this->filters->add(new Like($target, $value));

        return $this;
    }

    public function notLike(string $target, string|int|float|bool $value): self
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
        return $this->filters->collect();
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
        $result = $this->filters->map(fn (Filter $filter): array => $filter->toArray())->toArray();

        return $result;
    }
}
