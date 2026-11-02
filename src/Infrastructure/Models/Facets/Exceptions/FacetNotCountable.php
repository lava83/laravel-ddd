<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Facets\Exceptions;

use Exception;

class FacetNotCountable extends Exception
{
    public static function unknownRelation(string $facet, string $relation, string $model): self
    {
        return new self("Facet \"{$facet}\": {$model} has no relation \"{$relation}\".");
    }

    public static function unsupportedRelation(string $facet, string $relation, string $type): self
    {
        return new self("Facet \"{$facet}\": relation \"{$relation}\" is a {$type}; only HasMany and BelongsToMany (not MorphToMany) can be counted.");
    }

    public static function relationOnOtherConnection(string $facet, string $relation, string $connection, string $relatedConnection): self
    {
        return new self("Facet \"{$facet}\": relation \"{$relation}\" lives on connection \"{$relatedConnection}\", the list on \"{$connection}\". A relation facet is counted with a subquery and needs both on the same connection.");
    }

    public static function valueNotScalar(string $facet, mixed $value): self
    {
        return new self(sprintf('Facet "%s": the database returned a %s as a value; only scalars and null can be counted.', $facet, get_debug_type($value)));
    }
}
