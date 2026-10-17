<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\ApiAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\RefreshableAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\Unavailable;
use Throwable;

/**
 * The one door to an API: authentication, timeouts and the mapping of
 * failures to ApiException live here, so repositories only deal with paths and
 * payloads.
 *
 * Not bound by the package. The host builds it (or a thin subclass, to
 * type-hint one of several APIs) from an ApiClientConfig and an
 * ApiAuthenticator in its own service provider.
 */
class ApiClient
{
    public function __construct(
        protected readonly ApiClientConfig $config,
        protected readonly ApiAuthenticator $authenticator,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws ApiException
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function post(string $path, array $data = []): Response
    {
        return $this->send('POST', $path, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function patch(string $path, array $data = []): Response
    {
        return $this->send('PATCH', $path, ['json' => $data]);
    }

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws ApiException
     */
    public function delete(string $path, array $query = []): Response
    {
        return $this->send('DELETE', $path, ['query' => $query]);
    }

    /**
     * A 401 means a cached token was revoked or expired early, so it is
     * dropped and the request repeated once with a fresh one. Fixed
     * credentials get no repeat: the same credentials would only fail again.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ApiException
     */
    private function send(string $method, string $path, array $options = []): Response
    {
        try {
            $response = $this->request($method, $path, $options);

            if ($response->status() === 401 && $this->authenticator instanceof RefreshableAuthenticator) {
                $this->authenticator->invalidate();
                $response = $this->request($method, $path, $options);
            }
        } catch (ConnectionException $exception) {
            throw new Unavailable($exception->getMessage(), $method, $path, previous: $exception);
        }

        return $this->ensureSuccessful($response, $method, $path);
    }

    /**
     * Only reads are retried: without idempotency keys a repeated write could
     * be applied twice.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws ConnectionException
     * @throws ApiException
     */
    private function request(string $method, string $path, array $options): Response
    {
        $request = $this->authenticator->authorize($this->client());

        if ($method === 'GET' && $this->config->readAttempts > 1) {
            $request->retry(
                $this->config->readAttempts,
                $this->config->retryDelayMs,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );
        }

        return $request->send($method, $path, $options);
    }

    /**
     * @throws ApiException
     */
    private function ensureSuccessful(Response $response, string $method, string $path): Response
    {
        if ($response->failed()) {
            throw ApiException::fromResponse($method, $path, $response);
        }

        return $response;
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->config->baseUrl)
            ->contentType('application/json')
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->dontTruncateExceptions();
    }
}
