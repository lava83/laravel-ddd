<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods;

use Closure;
use Illuminate\Contracts\Database\Eloquent\SupportsPartialRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterMethod;
use IndexZer0\EloquentFiltering\Filter\Traits\FilterMethod\FilterContext\CustomFilter;
use Lava83\LaravelDdd\Infrastructure\Models\CrossDatabaseTables;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\SearchMode;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\Exceptions\RelationNotSearchable;

/**
 * Free-text search as an `indexzer0/eloquent-filtering` custom filter. Extend
 * it with the custom type and the searched columns, register it in
 * `eloquent-filtering.custom_filters`, allow it with `Filter::custom()` and
 * build it with `Filter\Builder::custom()`.
 *
 * The phrase is split on Unicode whitespace into tokens. Every token must
 * match (AND), and a token matches when any column matches (OR). Matching is
 * a LIKE with `!` as escape character, so `%`, `_` and `!` in a token are
 * literals on MySQL/MariaDB, SQLite and PostgreSQL alike. Case sensitivity
 * follows the column collation; PostgreSQL's LIKE is always case-sensitive.
 */
abstract class TokenSearchFilterMethod implements FilterMethod
{
    use CustomFilter;

    private const string ESCAPE = '!';

    /**
     * Named `$value`: eloquent-filtering passes the filter data to the
     * constructor by parameter name.
     */
    public function __construct(
        protected readonly string|int $value,
    ) {}

    /**
     * The searched columns: a column of the model (`email`) or a column of a
     * relation (`data.lastname`, `user.data.personalnr`). The part before the
     * last dot is the relation path. Its first relation may live on another
     * connection of the same database server, see {@see self::orWhereRelated()}.
     *
     * @return list<string>
     */
    abstract protected function columns(): array;

    /**
     * {@see SearchMode::Prefix} can use a B-tree index, {@see SearchMode::Contains} scans.
     */
    protected function mode(): SearchMode
    {
        return SearchMode::Prefix;
    }

    /**
     * Tokens beyond this count are ignored.
     */
    protected function maxTokens(): int
    {
        return 5;
    }

    /**
     * Rejects a value that is neither an integer nor a UTF-8 string as a
     * malformed filter, instead of a TypeError in the constructor.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public static function format(): array
    {
        return [
            'value' => [
                'present',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_int($value) && ! (is_string($value) && mb_check_encoding($value, 'UTF-8'))) {
                        $fail('The search phrase must be a UTF-8 string or an integer.');
                    }
                },
            ],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     *
     * @throws RelationNotSearchable
     */
    public function apply(Builder $query): Builder
    {
        $columns = $this->columnsByRelation();

        foreach ($this->tokens() as $token) {
            $pattern = $this->pattern($token);

            $query->where(function (Builder $query) use ($columns, $pattern): void {
                foreach ($columns as $relation => $relationColumns) {
                    if ($relation === '') {
                        $this->orLike($query, $relationColumns, $pattern);

                        continue;
                    }

                    $this->orWhereRelated($query, $relation, fn (Builder $related) => $related->where(
                        fn (Builder $related) => $this->orLike($related, $relationColumns, $pattern),
                    ));
                }
            });
        }

        return $query;
    }

    /**
     * A relation path whose first relation lives on the model's connection
     * goes to `whereHas()`.
     *
     * One on another connection becomes `whereIn(<key>, <related query
     * selecting its key>)`, the rest of the path a `whereHas()` inside it.
     * `whereHas()` would name the related table without its database, and
     * Laravel prefixes only the outermost table of a `whereIn` subquery, so
     * every table in it is qualified here. That needs both databases on one
     * server, readable through the list's connection.
     *
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>): mixed  $constraint
     *
     * @throws RelationNotSearchable
     */
    private function orWhereRelated(Builder $query, string $path, Closure $constraint): void
    {
        [$name, $rest] = array_pad(explode('.', $path, 2), 2, null);
        $model = $query->getModel();
        $relation = $model->isRelation($name) ? Relation::noConstraints(fn (): mixed => $model->{$name}()) : null;

        // getName() resolves the default connection; getConnectionName() would be null for it.
        $connection = $model->getConnection();

        // An unknown relation goes to whereHas() as well, which reports it.
        if (! $relation instanceof Relation || $relation->getRelated()->getConnection()->getName() === $connection->getName()) {
            $query->orWhereHas($path, $constraint);

            return;
        }

        $relatedConnection = $relation->getRelated()->getConnection();
        [$key, $relatedKey] = self::keys($relation, $name, $model, (string) $connection->getName(), (string) $relatedConnection->getName());
        $related = $relation->getQuery();

        if ($rest === null) {
            $constraint($related);
        } else {
            $related->whereHas($rest, $constraint);
        }

        $subquery = $related->toBase()->select($relatedKey);
        CrossDatabaseTables::qualify($subquery, $connection->getDatabaseName());

        $query->orWhereIn($key, $subquery);
    }

    /**
     * The key of the list model and the key of the related model that match.
     *
     * Without the parent constraint, a morph relation would lose its morph
     * type, and a one-of-many relation joins a subquery that is SQL already,
     * so its tables cannot be qualified.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array{string, string}
     *
     * @throws RelationNotSearchable
     */
    private static function keys(Relation $relation, string $name, Model $model, string $connection, string $relatedConnection): array
    {
        if ($relation instanceof BelongsTo && ! $relation instanceof MorphTo) {
            return [$relation->getQualifiedForeignKeyName(), $relation->getQualifiedOwnerKeyName()];
        }

        if ($relation instanceof HasOneOrMany
            && ! $relation instanceof MorphOneOrMany
            && ! ($relation instanceof SupportsPartialRelations && $relation->isOneOfMany())) {
            return [$relation->getQualifiedParentKeyName(), $relation->getQualifiedForeignKeyName()];
        }

        throw RelationNotSearchable::unsupportedOnOtherConnection($name, get_debug_type($relation), $model::class, $connection, $relatedConnection);
    }

    /**
     * The columns grouped by relation, the model's own under `''`, so a
     * relation costs one subquery per token, not one per column.
     *
     * @return array<string, list<string>>
     */
    private function columnsByRelation(): array
    {
        $grouped = [];

        foreach ($this->columns() as $column) {
            $separator = strrpos($column, '.');

            if ($separator === false) {
                $grouped[''][] = $column;

                continue;
            }

            $grouped[substr($column, 0, $separator)][] = substr($column, $separator + 1);
        }

        return $grouped;
    }

    /**
     * The tokens of the phrase, de-duplicated case-insensitively, first
     * occurrence wins, at most {@see self::maxTokens()}.
     *
     * @return list<string>
     */
    private function tokens(): array
    {
        $unique = [];

        foreach (preg_split('/\s+/u', (string) $this->value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $unique[mb_strtolower($token)] ??= $token;
        }

        return array_slice(array_values($unique), 0, max(0, $this->maxTokens()));
    }

    private function pattern(string $token): string
    {
        $literal = strtr($token, [
            self::ESCAPE => self::ESCAPE.self::ESCAPE,
            '%' => self::ESCAPE.'%',
            '_' => self::ESCAPE.'_',
        ]);

        return match ($this->mode()) {
            SearchMode::Prefix => $literal.'%',
            SearchMode::Contains => '%'.$literal.'%',
        };
    }

    /**
     * Laravel's `where($column, 'like', …)` has no ESCAPE clause, and the
     * default escape character differs per driver (and per `sql_mode` on
     * MySQL). A raw clause with an explicit one behaves the same everywhere.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns
     */
    private function orLike(Builder $query, array $columns, string $pattern): void
    {
        $grammar = $query->getQuery()->getGrammar();

        foreach ($columns as $column) {
            $query->orWhereRaw($grammar->wrap($query->qualifyColumn($column))." like ? escape '".self::ESCAPE."'", [$pattern]);
        }
    }
}
