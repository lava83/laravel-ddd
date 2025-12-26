<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Exceptions;

use RuntimeException;
use Throwable;

class CantDeleteModel extends RuntimeException
{
    public function __construct(string $message = 'Unable to delete model', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
