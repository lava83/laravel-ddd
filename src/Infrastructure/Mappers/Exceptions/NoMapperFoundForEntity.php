<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers\Exceptions;

use Exception;

class NoMapperFoundForEntity extends Exception
{
    public function __construct(string $entityClass)
    {
        parent::__construct(sprintf('No mapper found for entity class: %s', $entityClass));
    }

    public static function make(string $entityClass): self
    {
        return new self($entityClass);
    }
}
