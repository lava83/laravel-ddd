<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Base of every failure talking to an API. The client maps HTTP status codes
 * to the subclasses, so callers never see Laravel's RequestException.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $method,
        public readonly string $path,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    public static function fromResponse(string $method, string $path, Response $response): self
    {
        $message = sprintf('API %s %s failed with status %d', $method, $path, $response->status());

        return match (true) {
            $response->status() === 401 => new Unauthorized($message, $method, $path, $response),
            $response->status() === 403 => new Forbidden($message, $method, $path, $response),
            $response->status() === 404 => new NotFound($message, $method, $path, $response),
            $response->status() === 422 => new ValidationFailed($message, $method, $path, $response),
            $response->serverError() => new Unavailable($message, $method, $path, $response),
            default => new self($message, $method, $path, $response),
        };
    }
}
