<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd;

use Lava83\LaravelDdd\Infrastructure\Contracts\PolymorphicReferenceResolver;
use Lava83\LaravelDdd\Infrastructure\Mappers\PolymorphicReferenceMapper;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelDddServiceProvider extends PackageServiceProvider
{
    public function registeringPackage(): void
    {
        $this->bindSingletons();

        parent::registeringPackage();
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name('laravel-ddd');
    }

    private function bindSingletons(): void
    {
        $this->app->singleton(
            PolymorphicReferenceResolver::class,
            PolymorphicReferenceMapper::class,
        );
    }
}
