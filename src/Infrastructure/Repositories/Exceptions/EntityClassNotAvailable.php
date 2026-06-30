<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions;

use Exception;

class EntityClassNotAvailable extends Exception
{
    public static function make(string $repository): self
    {
        return new self("Entity class for {$repository} not available. Please add the entity class name to \$entityClassName property on model.");
    }
}
