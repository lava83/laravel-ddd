<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects;

use JsonSerializable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Stringable;

abstract class ValueObject implements JsonSerializable, Stringable
{
    /**
     * Subclasses assign the properties validate() reads and then call parent::__construct().
     *
     * @throws ValidationException
     */
    public function __construct()
    {
        $errors = $this->validate();

        if ($errors !== []) {
            throw ValidationException::fromArray($errors);
        }
    }

    /**
     * @return array<string, list<string>> Messages keyed by property name
     */
    abstract protected function validate(): array;
}
