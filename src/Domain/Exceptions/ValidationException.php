<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Exceptions;

use Exception;
use Throwable;

class ValidationException extends Exception
{
    /**
     * @param  array<string, list<string>>  $errors  Messages keyed by property name
     */
    public function __construct(
        string $message = 'Validation failed',
        int $code = 422,
        ?Throwable $previous = null,
        private readonly array $errors = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array<string, list<string>>  $errors  Messages keyed by property name
     */
    public static function fromArray(array $errors): self
    {
        $message = implode(' ', array_merge(...array_values($errors)));

        return new self($message, errors: $errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
