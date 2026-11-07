<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests;

use IndexZer0\EloquentFiltering\EloquentFilteringServiceProvider;
use Lava83\LaravelDdd\LaravelDddServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            EloquentFilteringServiceProvider::class,
            LaravelDddServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
