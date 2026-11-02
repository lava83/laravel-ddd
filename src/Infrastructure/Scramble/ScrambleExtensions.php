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
 * Scramble keeps rule transformers per API. They go to every API registered
 * by then, so also to one a service provider registered in its `boot()`, and
 * to the default API, which Scramble clones for every API registered later.
 * An API that has them already, by hand, keeps each once: Scramble drops
 * duplicate transformer classes.
 */
final class ScrambleExtensions
{
    public static function register(Application $app): void
    {
        if (! class_exists(Scramble::class) || $app->getProviders(ScrambleServiceProvider::class) === []) {
            return;
        }

        Scramble::registerExtension(FacetsResourceAddToExtension::class);

        foreach (Scramble::getConfigurationsInstance()->all() as $api) {
            $api->withRuleTransformers([
                SortParameterSchema::class,
                FiltersParameterSchema::class,
            ]);
        }
    }
}
