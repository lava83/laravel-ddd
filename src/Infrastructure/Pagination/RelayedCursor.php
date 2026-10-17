<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination;

use BadMethodCallException;
use Illuminate\Pagination\Cursor;

/**
 * A cursor taken over from another API: the token is opaque and is handed on
 * exactly as received. Laravel's Cursor::encode() would rebuild it from decoded
 * parameters, which is not guaranteed to give the same string back.
 */
final class RelayedCursor extends Cursor
{
    private function __construct(private readonly string $token, bool $pointsToNextItems)
    {
        parent::__construct([], $pointsToNextItems);
    }

    public static function next(string $token): self
    {
        return new self($token, true);
    }

    public static function previous(string $token): self
    {
        return new self($token, false);
    }

    public function encode(): string
    {
        return $this->token;
    }

    /**
     * The inherited factory would call the private constructor with decoded parameters.
     *
     * @throws BadMethodCallException
     */
    public static function fromEncoded($encodedString): never
    {
        throw new BadMethodCallException('A relayed cursor is built with next() or previous(), not decoded.');
    }
}
