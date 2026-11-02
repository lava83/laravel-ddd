<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use Lava83\LaravelDdd\Infrastructure\Scramble\FiltersParameterSchema;
use Lava83\LaravelDdd\Infrastructure\Scramble\SortParameterSchema;

/**
 * Registers APIs in `boot()`, as Scramble's docs show for several APIs: before
 * the package adds its rule transformers once the application has booted.
 */
final class SpecApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::registerApi('early');

        Scramble::registerApi('early-by-hand')->withRuleTransformers([
            SortParameterSchema::class,
            FiltersParameterSchema::class,
        ]);
    }
}
