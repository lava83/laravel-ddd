<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Auth;

use Illuminate\Http\Client\PendingRequest;
use SensitiveParameter;

/**
 * A flat token that never changes.
 */
final readonly class StaticBearerAuthenticator implements ApiAuthenticator
{
    public function __construct(#[SensitiveParameter] private string $token) {}

    public function authorize(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->token);
    }
}
