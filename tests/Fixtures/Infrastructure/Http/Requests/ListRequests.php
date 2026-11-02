<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a list request through the container, as Laravel does for a
 * controller argument: it validates on resolving.
 */
final class ListRequests
{
    /**
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $request
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json  a JSON body, sent with `Content-Type: application/json`
     * @return TRequest
     *
     * @throws ValidationException
     */
    public static function resolve(string $request, array $query = [], ?array $json = null): FormRequest
    {
        $base = $json === null
            ? Request::create('/list', 'GET', $query)
            : Request::create(
                '/list?'.http_build_query($query),
                'GET',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: (string) json_encode($json),
            );

        app()->instance('request', $base);

        return app($request);
    }

    /**
     * @param  class-string<FormRequest>  $request
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, array<int, string>> the validation errors, none if the request validates
     */
    public static function errors(string $request, array $query = [], ?array $json = null): array
    {
        try {
            self::resolve($request, $query, $json);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }
}
