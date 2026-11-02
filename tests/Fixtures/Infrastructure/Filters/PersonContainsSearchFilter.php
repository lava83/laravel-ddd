<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\SearchMode;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;

final class PersonContainsSearchFilter extends TokenSearchFilterMethod
{
    public static function type(): string
    {
        return '$personContainsSearch';
    }

    protected function columns(): array
    {
        return ['email', 'data.firstname', 'data.lastname'];
    }

    protected function mode(): SearchMode
    {
        return SearchMode::Contains;
    }
}
