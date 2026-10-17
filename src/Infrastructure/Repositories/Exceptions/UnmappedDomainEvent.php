<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions;

use LogicException;

/**
 * An aggregate recorded an event the repository has no API call for. Failing
 * loudly beats dropping the change: the API is the only place it is stored.
 */
final class UnmappedDomainEvent extends LogicException
{
    public static function for(string $eventClass, string $repositoryClass): self
    {
        return new self(sprintf('%s has no API command for %s', $repositoryClass, $eventClass));
    }
}
