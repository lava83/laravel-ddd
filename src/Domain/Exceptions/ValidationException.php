<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Exceptions;

use Exception;
use Throwable;

class ValidationException extends Exception
{
    public function __construct(string $message = 'Validation failed', int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array<string>  $errors
     */
    public static function fromArray(array $errors): self
    {
        $message = implode(', ', $errors);

        return new self($message);
    }
}
