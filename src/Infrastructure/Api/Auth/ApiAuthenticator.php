<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Auth;

use Illuminate\Http\Client\PendingRequest;

/**
 * How requests to an API identify themselves. Takes the whole request, so a
 * bearer token, an API-key header or basic auth are all the same shape.
 *
 * A strategy whose credentials can expire also implements
 * RefreshableAuthenticator; fixed credentials need nothing more.
 */
interface ApiAuthenticator
{
    public function authorize(PendingRequest $request): PendingRequest;
}
