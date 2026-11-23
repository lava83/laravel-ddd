<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\Exceptions;

use Exception;

class RelationNotSearchable extends Exception
{
    public static function unsupportedOnOtherConnection(string $relation, string $type, string $model, string $connection, string $relatedConnection): self
    {
        return new self("Search: relation \"{$relation}\" of {$model} is a {$type} on connection \"{$relatedConnection}\", the list on \"{$connection}\". On another connection, only BelongsTo, HasOne (not one-of-many) and HasMany can be searched.");
    }
}
