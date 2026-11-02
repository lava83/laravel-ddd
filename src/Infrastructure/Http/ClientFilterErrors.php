<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Http;

use Closure;
use Illuminate\Validation\ValidationException;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterException;
use IndexZer0\EloquentFiltering\Filter\Exceptions\DeniedFilterException;

/**
 * Turns the errors of client filters that only show up when the read query
 * runs into a 422 on `filters`: a target the model does not allow
 * ({@see DeniedFilterException}), or any other {@see FilterException}. Every
 * other exception passes through unchanged.
 *
 * Wrap the read query only, never a write: a write that fails on a filter is
 * not the client's error.
 */
final class ClientFilterErrors
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $read
     * @return TResult
     *
     * @throws ValidationException
     */
    public static function translate(Closure $read, string $attribute = 'filters'): mixed
    {
        try {
            return $read();
        } catch (FilterException $exception) {
            throw ValidationException::withMessages([$attribute => [$exception->getMessage()]]);
        }
    }
}
