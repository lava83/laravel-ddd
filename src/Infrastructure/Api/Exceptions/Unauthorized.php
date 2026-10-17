<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Exceptions;

/**
 * The API answers 401. With a refreshable authenticator this is the answer to
 * the repeat after the token was renewed once, which points at wrong
 * credentials rather than an expired token.
 */
final class Unauthorized extends ApiException {}
