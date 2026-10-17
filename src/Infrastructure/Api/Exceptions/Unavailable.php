<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Exceptions;

/**
 * The API answered 5xx or could not be reached at all.
 */
final class Unavailable extends ApiException {}
