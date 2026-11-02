<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Scramble;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Contracts\Foundation\Application;

/**
 * The Scramble extensions of the package. The service provider registers them
 * once the application has booted, if Scramble is installed and loaded.
 *
 * The rule transformers go to the default API and to every API registered
 * after it, which Scramble clones from the default one.
 */
final class ScrambleExtensions
{
    public static function register(Application $app): void
    {
        if (! class_exists(Scramble::class) || $app->getProviders(ScrambleServiceProvider::class) === []) {
            return;
        }

        Scramble::registerExtension(FacetsResourceAddToExtension::class);

        Scramble::configure()->withRuleTransformers([
            SortParameterSchema::class,
            FiltersParameterSchema::class,
        ]);
    }
}
