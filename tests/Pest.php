<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Lava83\LaravelDdd\Tests\TestCase;
use Illuminate\Support\Facades\Http;

pest()
    ->extend(TestCase::class)
    ->beforeEach(function () {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Foundation');
