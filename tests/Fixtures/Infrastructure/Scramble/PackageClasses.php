<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

use Dedoc\Scramble\Infer\Configuration\DefinitionMatcher;

/**
 * The classes of the package, which Scramble reads from their signatures and
 * PHPDoc only, as it reads a class in `vendor/` of a consumer. Without it,
 * Scramble analyses their code, and the spec here would differ from the one
 * a consumer gets.
 */
final class PackageClasses implements DefinitionMatcher
{
    public function matches(string $class): bool
    {
        return str_starts_with($class, 'Lava83\\LaravelDdd\\') && ! str_starts_with($class, 'Lava83\\LaravelDdd\\Tests\\');
    }
}
