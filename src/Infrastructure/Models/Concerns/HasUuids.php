<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids as HasUniqueStringIds;
use Ramsey\Uuid\Uuid;

trait HasUuids
{
    use HasUniqueStringIds;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }
}
