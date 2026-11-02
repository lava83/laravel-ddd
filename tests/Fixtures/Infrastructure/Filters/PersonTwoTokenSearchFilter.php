<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;

final class PersonTwoTokenSearchFilter extends TokenSearchFilterMethod
{
    public static function type(): string
    {
        return '$personTwoTokenSearch';
    }

    protected function columns(): array
    {
        return ['email', 'data.firstname', 'data.lastname'];
    }

    protected function maxTokens(): int
    {
        return 2;
    }
}
