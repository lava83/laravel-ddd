<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions;

use LogicException;

/**
 * A relation field of a sort is declared wrong: not `relation.column`, not a
 * relation of the list model, or a relation that has more than one row per
 * list row.
 */
class RelationNotSortable extends LogicException
{
    public static function malformedPath(string $field, string $path): self
    {
        return new self("Sort field \"{$field}\": \"{$path}\" must be a relation of the list model and a column of it, as in \"profile.personnel_number\".");
    }

    public static function unknownRelation(string $field, string $relation, string $model): self
    {
        return new self("Sort field \"{$field}\": \"{$relation}\" is not a relation of {$model}.");
    }

    public static function notToOne(string $field, string $relation, string $type, string $model): self
    {
        return new self("Sort field \"{$field}\": relation \"{$relation}\" of {$model} is a {$type}. Only BelongsTo and HasOne can be sorted by.");
    }

    public static function oneOfManyOnOtherConnection(string $field, string $relation, string $model, string $connection, string $relatedConnection): self
    {
        return new self("Sort field \"{$field}\": relation \"{$relation}\" of {$model} is a one-of-many HasOne on connection \"{$relatedConnection}\", the list on \"{$connection}\". On another connection, only BelongsTo and HasOne (not one-of-many) can be sorted by.");
    }
}
