<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClient;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClientConfig;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\ApiAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\ClientCredentialsAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\StaticBearerAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\Forbidden;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\NotFound;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\Unauthorized;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\Unavailable;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ValidationFailed;

function apiConfig(): ApiClientConfig
{
    return new ApiClientConfig('https://api.test', retryDelayMs: 0);
}

function oauthClient(): ApiClient
{
    return new ApiClient(apiConfig(), new ClientCredentialsAuthenticator(apiConfig(), 'id', 'secret', 'v1/oauth/token'));
}

function staticClient(?ApiAuthenticator $authenticator = null): ApiClient
{
    return new ApiClient(apiConfig(), $authenticator ?? new StaticBearerAuthenticator('flat-token'));
}

/**
 * @param  array<string, mixed>  $routes
 */
function fakeApi(array $routes = []): void
{
    Http::fake([
        '*/v1/oauth/token' => Http::response(['token_type' => 'Bearer', 'expires_in' => 600, 'access_token' => 'access-token']),
        ...$routes,
    ]);
}

function tokenRequests(): int
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), 'v1/oauth/token'))->count();
}

beforeEach(function (): void {
    Cache::flush();
});

describe('client credentials', function (): void {
    it('sends the bearer token and requests it only once for several calls', function (): void {
        fakeApi(['*/v1/ping' => Http::response(['data' => []])]);
        $client = oauthClient();

        $client->get('v1/ping');
        $client->get('v1/ping');

        expect(tokenRequests())->toBe(1);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'v1/ping')
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    });

    it('reads the token back from a cache that serializes, as Redis does', function (): void {
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
        fakeApi(['*/v1/ping' => Http::response(['data' => []])]);
        $client = oauthClient();

        $client->get('v1/ping');
        $client->get('v1/ping');

        expect(tokenRequests())->toBe(1);
    });

    it('fetches a fresh token and repeats the request once after a 401', function (): void {
        Http::fake([
            '*/v1/oauth/token' => Http::sequence()
                ->push(['access_token' => 'stale'])
                ->push(['access_token' => 'fresh']),
            '*/v1/ping' => Http::sequence()->push([], 401)->push(['data' => 'ok']),
        ]);

        $response = oauthClient()->get('v1/ping');

        expect($response->json('data'))->toBe('ok')
            ->and(tokenRequests())->toBe(2);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'v1/ping')
            && $request->hasHeader('Authorization', 'Bearer fresh'));
    });

    it('gives up with Unauthorized when the 401 survives the refresh', function (): void {
        fakeApi(['*/v1/ping' => Http::response([], 401)]);

        expect(fn () => oauthClient()->get('v1/ping'))->toThrow(Unauthorized::class);
        expect(tokenRequests())->toBe(2);
    });

    it('fails with Unauthorized when the credentials are rejected', function (): void {
        Http::fake(['*/v1/oauth/token' => Http::response(['message' => 'invalid_client'], 401)]);

        expect(fn () => oauthClient()->get('v1/ping'))->toThrow(Unauthorized::class);
    });

    it('fails loudly when the token response carries no access token', function (): void {
        Http::fake(['*/v1/oauth/token' => Http::response(['token_type' => 'Bearer'])]);

        expect(fn () => oauthClient()->get('v1/ping'))->toThrow(ApiException::class, 'no access_token');
        expect(Cache::has('laravel-ddd.api-token.'.sha1('https://api.test|id')))->toBeFalse();
    });

    it('keeps tokens of different APIs apart', function (): void {
        fakeApi(['*/v1/ping' => Http::response(['data' => []])]);
        $other = new ApiClientConfig('https://other.test');

        oauthClient()->get('v1/ping');
        (new ApiClient($other, new ClientCredentialsAuthenticator($other, 'id', 'secret', 'v1/oauth/token')))->get('v1/ping');

        expect(tokenRequests())->toBe(2);
    });
});

describe('static bearer', function (): void {
    it('sends the flat token and never asks for another', function (): void {
        fakeApi(['*/v1/ping' => Http::response(['data' => []])]);

        staticClient()->get('v1/ping');

        expect(tokenRequests())->toBe(0);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer flat-token'));
    });

    it('does not repeat a request after a 401', function (): void {
        Http::fake(['*/v1/ping' => Http::sequence()->push([], 401)->push(['data' => 'ok'])]);

        expect(fn () => staticClient()->get('v1/ping'))->toThrow(Unauthorized::class);
        Http::assertSentCount(1);
    });
});

describe('failures', function (): void {
    it('maps the status code to an exception', function (int $status, string $exception): void {
        fakeApi(['*/v1/ping' => Http::response(['message' => 'nope'], $status)]);

        expect(fn () => oauthClient()->patch('v1/ping'))->toThrow($exception);
    })->with([
        'forbidden' => [403, Forbidden::class],
        'not found' => [404, NotFound::class],
        'validation' => [422, ValidationFailed::class],
        'server error' => [503, Unavailable::class],
        'anything else' => [409, ApiException::class],
    ]);

    it('exposes the field errors of a validation failure', function (): void {
        fakeApi(['*/v1/ping' => Http::response([
            'message' => 'The user id field is required.',
            'errors' => ['user_id' => ['The user id field is required.']],
        ], 422)]);

        try {
            oauthClient()->patch('v1/ping');
            $this->fail('Expected a validation failure');
        } catch (ValidationFailed $exception) {
            expect($exception->errors())->toBe(['user_id' => ['The user id field is required.']]);
        }
    });

    it('reports an unreachable API as Unavailable', function (): void {
        fakeApi(['*/v1/ping' => fn () => throw new ConnectionException('connection refused')]);

        expect(fn () => oauthClient()->patch('v1/ping'))->toThrow(Unavailable::class, 'connection refused');
    });
});

describe('retries', function (): void {
    it('retries a read after a server error', function (): void {
        fakeApi(['*/v1/ping' => Http::sequence()->push([], 500)->push(['data' => 'ok'])]);

        expect(oauthClient()->get('v1/ping')->json('data'))->toBe('ok');
    });

    it('retries a read after a connection error', function (): void {
        fakeApi(['*/v1/ping' => Http::sequence()
            ->pushFailedConnection()
            ->push(['data' => 'ok']),
        ]);

        expect(oauthClient()->get('v1/ping')->json('data'))->toBe('ok');
    });

    it('does not retry a read after a client error', function (): void {
        fakeApi(['*/v1/ping' => Http::sequence()->push([], 404)->push(['data' => 'ok'])]);

        expect(fn () => oauthClient()->get('v1/ping'))->toThrow(NotFound::class);
        Http::assertSentCount(2); // token + one attempt
    });

    it('never retries a write, it could be applied twice', function (): void {
        fakeApi(['*/v1/ping' => Http::sequence()->push([], 500)->push(['data' => 'ok'])]);

        expect(fn () => oauthClient()->patch('v1/ping', ['user_id' => 1]))->toThrow(Unavailable::class);
        Http::assertSentCount(2); // token + one attempt
    });

    it('does not retry reads when read attempts is 1', function (): void {
        fakeApi(['*/v1/ping' => Http::sequence()->push([], 500)->push(['data' => 'ok'])]);
        $config = new ApiClientConfig('https://api.test', readAttempts: 1);

        expect(fn () => (new ApiClient($config, new StaticBearerAuthenticator('t')))->get('v1/ping'))
            ->toThrow(Unavailable::class);
        Http::assertSentCount(1);
    });
});

describe('requests', function (): void {
    it('sends a nested filter as a deepObject query', function (): void {
        fakeApi(['*/v1/ping*' => Http::response(['data' => []])]);

        oauthClient()->get('v1/ping', ['filter' => ['driver' => 'email']]);

        Http::assertSent(fn (Request $request) => str_ends_with(urldecode($request->url()), 'v1/ping?filter[driver]=email'));
    });

    it('sends the payload of a write as json', function (): void {
        fakeApi(['*/v1/ping' => Http::response(['data' => []])]);

        oauthClient()->patch('v1/ping', ['user_id' => 5]);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request->data() === ['user_id' => 5]);
    });

    it('sends post and delete with their verbs', function (): void {
        fakeApi(['*/v1/ping*' => Http::response(['data' => []])]);

        staticClient()->post('v1/ping', ['a' => 1]);
        staticClient()->delete('v1/ping', ['b' => 2]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === ['a' => 1]);
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
    });
});
