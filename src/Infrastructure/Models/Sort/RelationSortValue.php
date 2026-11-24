<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Lava83\LaravelDdd\Infrastructure\Models\CrossDatabaseTables;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;

/**
 * The value a relation field sorts a list row by: a correlated subquery that
 * selects the smallest value of the column among the related rows, NULL
 * without one. It is built as Laravel's `withMin()` builds its subquery, so the
 * constraints of the relation method and the global scopes of the related
 * model apply, and a row that a soft-delete scope hides counts as missing.
 *
 * A subquery, not a join: the list keeps one row per list row and its own
 * columns, and filters with unqualified columns stay unambiguous.
 *
 * @internal used by {@see Sort}
 */
final class RelationSortValue
{
    /**
     * @param  EloquentBuilder<*>  $list
     * @param  string  $path  the relation and its column, `profile.personnel_number`
     *
     * @throws RelationNotSortable the relation is unknown, not to-one, or one-of-many on another connection
     */
    public static function query(EloquentBuilder $list, string $field, string $path): QueryBuilder
    {
        // Sort::fromArray() has checked the path already.
        [$name, $column] = explode('.', $path, 2);
        $model = $list->getModel();
        $relation = $model->isRelation($name) ? Relation::noConstraints(fn (): mixed => $model->{$name}()) : null;

        if (! $relation instanceof Relation) {
            throw RelationNotSortable::unknownRelation($field, $name, $model::class);
        }

        if (! ($relation instanceof BelongsTo && ! $relation instanceof MorphTo) && ! $relation instanceof HasOne) {
            throw RelationNotSortable::notToOne($field, $name, get_debug_type($relation), $model::class);
        }

        $related = $relation->getRelated();
        $otherConnection = CrossDatabaseTables::onOtherConnection($model, $related);

        // Its join is a subquery that is SQL already, so its tables cannot be qualified.
        if ($otherConnection && $relation instanceof HasOne && $relation->isOneOfMany()) {
            throw RelationNotSortable::oneOfManyOnOtherConnection($field, $name, $model::class, (string) $model->getConnection()->getName(), (string) $related->getConnection()->getName());
        }

        // A relation to the list's own table aliases the related table, and the column has to name the alias.
        $qualified = $list->getQuery()->from === $relation->getQuery()->getQuery()->from
            ? $relation->getRelationCountHash(false).'.'.$column
            : $related->qualifyColumn($column);

        // Laravel only reads its table, to detect a relation to the list's own table.
        $parent = $model->newQueryWithoutScopes();
        $parent->getQuery()->from = $list->getQuery()->from;

        $existence = $relation->getRelationExistenceQuery(
            $related->newQuery(),
            $parent,
            new Expression('min('.$list->getQuery()->getGrammar()->wrap($qualified).')'),
        );
        $existence->getQuery()->setBindings([], 'select');

        $query = $existence->mergeConstraintsFrom($relation->getQuery())->toBase();
        $query->orders = null;
        $query->setBindings([], 'order');

        if ($otherConnection) {
            CrossDatabaseTables::qualify($query, $model->getConnection()->getDatabaseName());
        }

        return $query;
    }
}
