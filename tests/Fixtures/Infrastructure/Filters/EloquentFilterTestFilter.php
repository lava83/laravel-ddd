<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lava83\LaravelDdd\Infrastructure\Eloquent\Filters\EloquentQueryFilter;

/**
 * Two callables on purpose: a where clause and an ordering, so the order in
 * which apply() runs them is visible in the SQL.
 */
final class EloquentFilterTestFilter extends EloquentQueryFilter
{
    public function __construct(
        private readonly string $target,
        private readonly string|int|float|bool $value,
    ) {}

    public static function fromArray(array $data): static
    {
        /** @var array<int, string|int|float|bool>|string|int|float|bool $value */
        $value = $data['value'];

        return new self($data['target'], is_array($value) ? (string) $value[0] : $value);
    }

    protected function filters(): array
    {
        return [
            'where' => fn (Builder $query): Builder => $query->where($this->target, $this->value),
            'order' => fn (Builder $query): Builder => $query->orderBy($this->target),
        ];
    }
}
