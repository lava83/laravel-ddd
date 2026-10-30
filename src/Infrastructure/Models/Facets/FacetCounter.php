<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Facets;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetBucketReadModel;
use Lava83\LaravelDdd\Domain\ReadModels\Facets\FacetReadModel;
use Lava83\LaravelDdd\Infrastructure\Models\Facets\Exceptions\FacetNotCountable;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\MergeStrategy;
use stdClass;

/**
 * Counts the facets of a list on the database, with the base query the list
 * uses for its page.
 *
 * Each facet is counted with the whole scope filter plus every requested
 * filter except the facet's own, so a tab shows how many rows it would hold.
 * The scope is never reduced: it is what the caller may see at all.
 */
final class FacetCounter
{
    /**
     * A column facet groups the list query (`count(*)`), so `$baseQuery` must
     * not join a to-many table, or rows are counted once per joined row.
     *
     * @template TModel of Model
     *
     * @param  Closure(Builder): EloquentBuilder<TModel>  $baseQuery  the base query of the list page, including `->filter($filter->toArray())`
     * @param  Builder  $scope  what the caller may see (tenant, branch); applied in full to every facet
     * @param  Builder  $requested  the client's filters; each facet drops those on its own filter target
     * @return Collection<int, FacetReadModel> in the order of `$facets`
     *
     * @throws FacetNotCountable
     */
    public static function count(Closure $baseQuery, Builder $scope, Builder $requested, FacetDefinition ...$facets): Collection
    {
        $readModels = [];

        foreach ($facets as $facet) {
            $query = self::forCounting($baseQuery(self::filterFor($facet, $scope, $requested)));
            $relation = $facet->relationName();

            $readModels[] = new FacetReadModel(
                $facet->key(),
                $relation === null
                    ? self::countColumn($query, $facet)
                    : self::countRelation($query, $facet, $relation),
            );
        }

        return new Collection($readModels);
    }

    /**
     * The scope in full, plus the requested filters without the facet's own.
     *
     * Only `$requested` is ever reduced. A scope filter can share the facet's
     * target (`branch_id`), and dropping it would count rows the caller must
     * not see.
     */
    private static function filterFor(FacetDefinition $facet, Builder $scope, Builder $requested): Builder
    {
        return $scope->merge($requested->without($facet->filterTarget()), MergeStrategy::KeepExisting);
    }

    /**
     * Drop what only the page needs. Ordering breaks the grouping, and
     * MariaDB rejects a LIMIT inside an IN subquery.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     */
    private static function forCounting(EloquentBuilder $query): EloquentBuilder
    {
        $query->setEagerLoads([])->reorder();

        $base = $query->getQuery();
        $base->limit = null;
        $base->offset = null;

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return list<FacetBucketReadModel>
     *
     * @throws FacetNotCountable
     */
    private static function countColumn(EloquentBuilder $query, FacetDefinition $facet): array
    {
        $column = $query->qualifyColumn($facet->columnName());

        return self::buckets($facet, $query->toBase()
            ->select($column.' as facet_value')
            ->selectRaw('count(*) as facet_count')
            ->groupBy($column)
            ->get());
    }

    /**
     * Groups the related rows instead of joining them into the list query:
     * `whereIn(<foreign key>, <list query selecting the parent key>)` keeps the
     * parent's filters and scopes in one subquery, and `count(distinct …)`
     * counts each parent once per value. The related model's query keeps its
     * global scopes (soft deletes) and the relation's own constraints.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return list<FacetBucketReadModel>
     *
     * @throws FacetNotCountable
     */
    private static function countRelation(EloquentBuilder $query, FacetDefinition $facet, string $name): array
    {
        $model = $query->getModel();
        $relation = self::relation($model, $facet, $name);

        // getName() resolves the default connection; getConnectionName() would be null for it.
        $connection = $model->getConnection()->getName();
        $relatedConnection = $relation->getRelated()->getConnection()->getName();

        if ($connection !== $relatedConnection) {
            throw FacetNotCountable::relationOnOtherConnection($facet->key(), $name, (string) $connection, (string) $relatedConnection);
        }

        $foreignKey = $relation instanceof BelongsToMany
            ? $relation->getQualifiedForeignPivotKeyName()
            : $relation->getQualifiedForeignKeyName();
        $column = $relation->getRelated()->qualifyColumn($facet->columnName());
        $related = $relation->getQuery()->toBase();

        return self::buckets($facet, $related
            ->select($column.' as facet_value')
            ->selectRaw('count(distinct '.$related->getGrammar()->wrap($foreignKey).') as facet_count')
            ->whereIn($foreignKey, $query->toBase()->select($relation->getQualifiedParentKeyName()))
            ->groupBy($column)
            ->get());
    }

    /**
     * The relation without its parent constraint: it must reach the related
     * rows of every parent in the list, not of one.
     *
     * @return HasMany<Model, Model>|BelongsToMany<Model, Model>
     *
     * @throws FacetNotCountable
     */
    private static function relation(Model $model, FacetDefinition $facet, string $name): HasMany|BelongsToMany
    {
        if (! $model->isRelation($name)) {
            throw FacetNotCountable::unknownRelation($facet->key(), $name, $model::class);
        }

        $relation = Relation::noConstraints(fn (): mixed => $model->{$name}());

        // A MorphToMany would lose its morph type along with the parent constraint.
        if ($relation instanceof MorphToMany || ! ($relation instanceof HasMany || $relation instanceof BelongsToMany)) {
            throw FacetNotCountable::unsupportedRelation($facet->key(), $name, get_debug_type($relation));
        }

        return $relation;
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<FacetBucketReadModel>
     *
     * @throws FacetNotCountable
     */
    private static function buckets(FacetDefinition $facet, Collection $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $value = $row->facet_value;

            if ($value !== null && ! is_scalar($value)) {
                throw FacetNotCountable::valueNotScalar($facet->key(), $value);
            }

            $buckets[] = new FacetBucketReadModel($value, (int) $row->facet_count);
        }

        return $buckets;
    }
}
