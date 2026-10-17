<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Auth;

/**
 * Credentials that can be revoked or expire early. After a 401 the client
 * calls invalidate() and repeats the request once; an authenticator without
 * this interface gets no repeat, because the same credentials would only fail
 * again.
 */
interface RefreshableAuthenticator extends ApiAuthenticator
{
    public function invalidate(): void;
}
