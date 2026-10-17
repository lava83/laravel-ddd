<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api;

/**
 * Connection settings of one API. Built by the host application, so several
 * APIs can live side by side without a shared config key.
 */
final readonly class ApiClientConfig
{
    /**
     * @param  string  $baseUrl  host (and optional prefix) every path is relative to
     * @param  int  $timeout  seconds
     * @param  int  $readAttempts  total attempts for a GET after a connection error or 5xx (1 disables retrying);
     *                             writes are never retried
     * @param  int  $retryDelayMs  pause between two attempts of a read
     */
    public function __construct(
        public string $baseUrl,
        public int $timeout = 30,
        public int $readAttempts = 2,
        public int $retryDelayMs = 200,
    ) {}
}
