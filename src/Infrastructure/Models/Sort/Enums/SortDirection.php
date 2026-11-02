<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums;

enum SortDirection: string
{
    case Ascending = 'asc';

    /**
     * Requested with a leading `-` (`-created_at`).
     */
    case Descending = 'desc';
}
