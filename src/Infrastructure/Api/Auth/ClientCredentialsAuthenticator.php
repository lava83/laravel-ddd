<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClientConfig;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use SensitiveParameter;

/**
 * OAuth2 client-credentials grant. The access token is cached as a plain
 * string, so the host needs no `cache.serializable_classes` entry.
 */
final class ClientCredentialsAuthenticator implements RefreshableAuthenticator
{
    private readonly string $cacheKey;

    /**
     * @param  string  $tokenPath  relative to the config's base URL
     * @param  int  $ttl  seconds the token stays cached; keep it below the token lifetime
     * @param  string|null  $cacheKey  defaults to a key derived from base URL and client id, so two APIs never share a token
     */
    public function __construct(
        private readonly ApiClientConfig $config,
        private readonly string $clientId,
        #[SensitiveParameter]
        private readonly string $clientSecret,
        private readonly string $tokenPath = 'oauth/token',
        private readonly string $grantType = 'client_credentials',
        private readonly int $ttl = 540,
        ?string $cacheKey = null,
    ) {
        $this->cacheKey = $cacheKey ?? 'laravel-ddd.api-token.'.sha1($config->baseUrl.'|'.$clientId);
    }

    /**
     * @throws ConnectionException
     * @throws ApiException
     */
    public function authorize(PendingRequest $request): PendingRequest
    {
        return $request->withToken($this->token());
    }

    public function invalidate(): void
    {
        cache()->forget($this->cacheKey);
    }

    /**
     * @throws ConnectionException
     * @throws ApiException
     */
    private function token(): string
    {
        /** @var string */
        return cache()->remember($this->cacheKey, $this->ttl, fn (): string => $this->fetchToken());
    }

    /**
     * @throws ConnectionException
     * @throws ApiException
     */
    private function fetchToken(): string
    {
        $response = Http::baseUrl($this->config->baseUrl)
            ->contentType('application/json')
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->dontTruncateExceptions()
            ->post($this->tokenPath, [
                'grant_type' => $this->grantType,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if ($response->failed()) {
            throw ApiException::fromResponse('POST', $this->tokenPath, $response);
        }

        $token = $response->json('access_token');

        // Failing loudly: caching an empty token would turn every call into a 401.
        if (! is_string($token) || $token === '') {
            throw new ApiException('Token response has no access_token', 'POST', $this->tokenPath, $response);
        }

        return $token;
    }
}
