<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

class EntityClassNotAvailable extends Exception
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function make(string $modelClass): self
    {
        return new self("Entity class for {$modelClass} not available. Please add the entity class name to \$entityClassName property on model.");
    }
}
