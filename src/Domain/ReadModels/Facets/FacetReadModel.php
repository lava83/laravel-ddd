<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ReadModels\Facets;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ReadModels\ReadModel;

/**
 * The counts per value of one attribute of a list, e.g. for the tabs of an
 * admin UI.
 *
 * Buckets are ordered by value ascending, the null bucket first. Only values
 * that occur in the list have a bucket: a missing value means a count of 0.
 * String values are ordered by PHP comparison, not by database collation.
 */
final class FacetReadModel extends ReadModel
{
    /**
     * @var Collection<int, FacetBucketReadModel>
     */
    private readonly Collection $buckets;

    /**
     * @param  iterable<FacetBucketReadModel>  $buckets
     */
    public function __construct(
        private readonly string $key,
        iterable $buckets = [],
    ) {
        $this->buckets = (new Collection($buckets))
            ->sort(self::compare(...))
            ->values();
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return Collection<int, FacetBucketReadModel>
     */
    public function buckets(): Collection
    {
        return $this->buckets->collect();
    }

    /**
     * @return list<array{value: string|int|float|bool|null, count: int}>
     */
    public function toArray(): array
    {
        return array_values(
            $this->buckets->map(fn (FacetBucketReadModel $bucket): array => $bucket->toArray())->all(),
        );
    }

    private static function compare(FacetBucketReadModel $a, FacetBucketReadModel $b): int
    {
        return match (true) {
            $a->value() === null => $b->value() === null ? 0 : -1,
            $b->value() === null => 1,
            default => $a->value() <=> $b->value(),
        };
    }
}
