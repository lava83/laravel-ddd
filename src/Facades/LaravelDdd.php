<?php

namespace Lava83\LaravelDdd\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lava83\LaravelDdd\LaravelDdd
 */
class LaravelDdd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lava83\LaravelDdd\LaravelDdd::class;
    }
}
