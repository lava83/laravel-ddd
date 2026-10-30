<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use IndexZer0\EloquentFiltering\Filter\Contracts\FilterMethod;
use IndexZer0\EloquentFiltering\Filter\Traits\FilterMethod\FilterContext\CustomFilter;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums\SearchMode;

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
     * relation (`data.lastname`). The part before the last dot goes to
     * `whereHas()`, so the relation must live on the same connection.
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

                    $query->orWhereHas($relation, fn (Builder $related) => $related->where(
                        fn (Builder $related) => $this->orLike($related, $relationColumns, $pattern),
                    ));
                }
            });
        }

        return $query;
    }

    /**
     * The columns grouped by relation, the model's own under `''`, so a
     * relation costs one EXISTS subquery per token, not one per column.
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
