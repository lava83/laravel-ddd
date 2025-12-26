<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Actions;

use Mockery;
use Mockery\ExpectationInterface;
use Mockery\HigherOrderMessage;
use Mockery\MockInterface;

abstract class Action
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function mock(): MockInterface
    {
        $instance = static::make();

        if ($instance instanceof MockInterface) {
            return $instance;
        }

        return tap(Mockery::getContainer()->mock(static::class), fn() => app()->instance(static::class, $instance));
    }

    public static function shouldExecute(): ExpectationInterface|HigherOrderMessage
    {
        return static::mock()->shouldReceive('execute');
    }
}
