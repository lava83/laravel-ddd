<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters;

use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\TokenSearchFilterMethod;

/**
 * Takes its columns in the constructor, so each test names what it searches.
 * Built and applied by hand, not through eloquent-filtering.
 */
final class AccountSearchFilter extends TokenSearchFilterMethod
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(string|int $value, private readonly array $columns)
    {
        parent::__construct($value);
    }

    public static function type(): string
    {
        return '$accountSearch';
    }

    protected function columns(): array
    {
        return $this->columns;
    }
}
