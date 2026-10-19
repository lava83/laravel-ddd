<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Facades\LaravelDdd as LaravelDddFacade;
use Lava83\LaravelDdd\LaravelDdd;

describe('LaravelDdd facade', function (): void {
    it('resolves the LaravelDdd class from the container', function (): void {
        expect(LaravelDddFacade::getFacadeRoot())->toBeInstanceOf(LaravelDdd::class);
    });
});
