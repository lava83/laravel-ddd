<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums;

enum SearchMode
{
    /**
     * A token matches the start of the column value (`token%`). Can use a
     * B-tree index on the column.
     */
    case Prefix;

    /**
     * A token matches anywhere in the column value (`%token%`). Cannot use a
     * B-tree index, so the column is scanned.
     */
    case Contains;
}
