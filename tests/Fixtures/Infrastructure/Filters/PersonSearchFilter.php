<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;

/**
 * Prefix search with the defaults (five tokens).
 */
final class PersonSearchFilter extends TokenSearchFilterMethod
{
    public static function type(): string
    {
        return '$personSearch';
    }

    protected function columns(): array
    {
        return ['email', 'data.firstname', 'data.lastname'];
    }
}
