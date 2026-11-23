<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Sort;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Enums\SortDirection;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\RelationNotSortable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotApplicable;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Exceptions\SortNotValid;
use Lava83\LaravelDdd\Infrastructure\Pagination\SortedCursorPaginator;

/**
 * The order of a list: the fields a client asked for, then the key of the
 * list as the tie-breaker, so the order is unique, as cursor pagination needs.
 * The order ends early at a field that is unique already: the key itself or a
 * field declared unique that never holds NULL.
 *
 * The key follows the direction of the last field, so an index on that field
 * serves the order in both directions: the index carries the key ascending,
 * and read backwards, descending.
 *
 * A field is a column of the list model or a column of a to-one relation
 * under a name of its own. A field that may be NULL (a nullable column, every
 * relation field) sorts NULL as the greatest value, last ascending and first
 * descending, on every database. Only {@see self::paginate()} pages over it:
 * Laravel's cursor pagination compares with `>` and `<`, which never match
 * NULL.
 *
 * The request produces it and the read query applies it, so both use the one
 * key the request declares.
 */
final readonly class Sort
{
    /**
     * The alias a relation field is selected as, followed by its name.
     */
    public const string RELATION_ALIAS_PREFIX = 'laravel_ddd_sort_';

    /**
     * @param  list<SortField>  $fields
     * @param  list<string>  $unique
     */
    private function __construct(
        private string $key,
        private array $fields,
        private array $unique = [],
    ) {}

    /**
     * The order of a list without a requested sort: the key ascending.
     */
    public static function byKey(string $key): self
    {
        return new self($key, []);
    }

    /**
     * Parse the fields a client asked for, e.g. `['-created_at', 'email']`.
     * A leading `-` means descending, and the first field sorts first.
     *
     * @param  array<array-key, mixed>  $fields
     * @param  list<string>  $allowed  the fields a client may sort by: columns of the list model and names of `$relations`
     * @param  list<string>  $unique  the sortable columns that are unique per row, e.g. `email` with a unique index
     * @param  list<string>  $nullable  the sortable columns that may hold NULL
     * @param  array<string, string>  $relations  the sortable fields that are a column of a to-one relation, by name: `['personnel_number' => 'profile.personnel_number']`
     *
     * @throws SortNotValid
     * @throws RelationNotSortable a path of `$relations` is not `relation.column`
     */
    public static function fromArray(
        array $fields,
        array $allowed,
        string $key,
        array $unique = [],
        array $nullable = [],
        array $relations = [],
    ): self {
        // Every declared path, not only the requested ones, so a wrong one fails on any sort.
        foreach ($relations as $name => $path) {
            if (preg_match('/^[^.]+\.[^.]+$/', $path) !== 1) {
                throw RelationNotSortable::malformedPath($name, $path);
            }
        }

        $parsed = [];

        foreach ($fields as $field) {
            if (! is_string($field)) {
                throw SortNotValid::malformedField($field);
            }

            $column = str_starts_with($field, '-') ? substr($field, 1) : $field;

            if ($column === '') {
                throw SortNotValid::malformedField($field);
            }

            if (! in_array($column, $allowed, true)) {
                throw SortNotValid::unknownField($column, $allowed);
            }

            if (array_key_exists($column, $parsed)) {
                throw SortNotValid::duplicateField($column);
            }

            $parsed[$column] = new SortField(
                $column,
                $column === $field ? SortDirection::Ascending : SortDirection::Descending,
                in_array($column, $nullable, true),
                $relations[$column] ?? null,
            );
        }

        return new self($key, array_values($parsed), $unique);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * The fields the client asked for, without the key tie-breaker.
     *
     * @return list<SortField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The order columns as applied: the fields up to the first that is unique,
     * the key or a unique field that is not nullable, as the fields after it
     * never decide. Without such a field, all fields, then the key in the
     * direction of the last one, ascending without fields.
     *
     * @return list<SortField>
     */
    public function orders(): array
    {
        $orders = [];

        foreach ($this->fields as $field) {
            $orders[] = $field;

            if ($field->column() === $this->key || (in_array($field->column(), $this->unique, true) && ! $field->isNullable())) {
                return $orders;
            }
        }

        $last = $this->fields === [] ? null : $this->fields[array_key_last($this->fields)];

        return [...$orders, new SortField($this->key, $last?->direction() ?? SortDirection::Ascending)];
    }

    /**
     * The parameters a cursor of this order carries: one per order column,
     * named as the column or the relation field.
     *
     * @return list<string>
     */
    public function cursorParameters(): array
    {
        return array_map(fn (SortField $field): string => $field->column(), $this->orders());
    }

    /**
     * The parameters a cursor of this order may carry as NULL.
     *
     * @return list<string>
     */
    public function nullableCursorParameters(): array
    {
        return array_values(array_map(
            fn (SortField $field): string => $field->column(),
            array_filter($this->orders(), fn (SortField $field): bool => $field->isNullable()),
        ));
    }

    /**
     * Order `$query` by {@see self::orders()}, replacing any order it had. The
     * columns stay unqualified, so the cursor parameters are named exactly as
     * {@see self::cursorParameters()} says.
     *
     * For `cursorPaginate()` after it, so only for an order without a field
     * that may be NULL. Page such an order with {@see self::paginate()}.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     *
     * @throws SortNotApplicable a field of the order may be NULL
     */
    public function apply(EloquentBuilder $query): EloquentBuilder
    {
        foreach ($this->orders() as $field) {
            if ($field->isNullable()) {
                throw SortNotApplicable::nullableField($field->column());
            }
        }

        $query->reorder();

        foreach ($this->orders() as $field) {
            $query->orderBy($field->column(), $field->direction()->value);
        }

        return $query;
    }

    /**
     * One page of `$query` in this order, as `cursorPaginate()` would return
     * it, for every kind of field. The cursor constraint is built here: after
     * a value, a nullable field also matches NULL where NULL comes later, and
     * after NULL, it matches NULL and then the next field.
     *
     * A relation field is selected as {@see self::RELATION_ALIAS_PREFIX} and
     * its name, the cursor value is read from there. The columns of the list
     * model stay as they are.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @param  Cursor|string|null  $cursor  the cursor, or null for the one of the current request under `$cursorName`
     * @return SortedCursorPaginator<int, TModel>
     *
     * @throws RelationNotSortable a relation field is not a BelongsTo or HasOne of the list model
     */
    public function paginate(EloquentBuilder $query, int $perPage, string $cursorName = 'cursor', Cursor|string|null $cursor = null): SortedCursorPaginator
    {
        if (! $cursor instanceof Cursor) {
            $cursor = is_string($cursor) ? Cursor::fromEncoded($cursor) : CursorPaginator::resolveCurrentCursor($cursorName);
        }

        // A previous page is read backwards from the cursor, and the paginator turns it round again.
        $orders = $cursor?->pointsToPreviousItems() === true
            ? array_map(fn (SortField $field): SortField => $field->reversed(), $this->orders())
            : $this->orders();

        $operands = $this->operands($query, $orders);
        $grammar = $query->getQuery()->getGrammar();
        // PostgreSQL sorts NULL as the greatest value already, and does not take an alias in an expression.
        $placeNulls = $query->getModel()->getConnection()->getDriverName() !== 'pgsql';

        $query->reorder();

        foreach ($orders as $field) {
            $direction = $field->direction()->value;
            $column = $operands[$field->column()]['order'];

            if ($field->isNullable() && $placeNulls) {
                $query->orderByRaw('case when '.$grammar->wrap($column).' is null then 1 else 0 end '.$direction);
            }

            $query->orderBy($column, $direction);
        }

        if ($cursor !== null) {
            self::whereAfter($query, $orders, $operands, $cursor);
        }

        $query->limit($perPage + 1);

        return new SortedCursorPaginator($query->get(), $perPage, $cursor, [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $this->cursorParameters(),
            'nullableParameters' => $this->nullableCursorParameters(),
            'parameterAttributes' => array_filter(array_map(
                fn (array $operand): ?string => $operand['attribute'],
                $operands,
            )),
        ]);
    }

    /**
     * What each order field is ordered and compared by: the column, or for a
     * relation field its alias and its subquery, which is selected here.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @param  list<SortField>  $orders
     * @return array<string, array{order: string, compare: string, bindings: list<mixed>, attribute: string|null}>
     *
     * @throws RelationNotSortable
     */
    private function operands(EloquentBuilder $query, array $orders): array
    {
        $grammar = $query->getQuery()->getGrammar();
        $operands = [];

        foreach ($orders as $field) {
            $relation = $field->relation();

            if ($relation === null) {
                $operands[$field->column()] = ['order' => $field->column(), 'compare' => $grammar->wrap($field->column()), 'bindings' => [], 'attribute' => null];

                continue;
            }

            $value = RelationSortValue::query($query, $field->column(), $relation);
            $alias = self::RELATION_ALIAS_PREFIX.$field->column();
            $sql = '('.$value->toSql().')';
            /** @var list<mixed> $bindings */
            $bindings = $value->getBindings();

            // As withAggregate() does: without it, the list would select the relation field alone.
            if ($query->getQuery()->columns === null) {
                $query->select($query->qualifyColumn('*'));
            }

            $query->selectRaw($sql.' as '.$grammar->wrap($alias), $bindings);
            $operands[$field->column()] = ['order' => $alias, 'compare' => $sql, 'bindings' => $bindings, 'attribute' => $alias];
        }

        return $operands;
    }

    /**
     * Keeps the rows after the cursor row: after its value in the first
     * field, or with its value and after it in the next field, down to the
     * last. NULL is the greatest value: after a value ascending comes NULL,
     * after NULL descending every value.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @param  non-empty-list<SortField>  $orders
     * @param  array<string, array{order: string, compare: string, bindings: list<mixed>, attribute: string|null}>  $operands
     */
    private static function whereAfter(EloquentBuilder $query, array $orders, array $operands, Cursor $cursor): void
    {
        $field = $orders[0];
        $rest = array_slice($orders, 1);
        ['compare' => $sql, 'bindings' => $bindings] = $operands[$field->column()];
        $value = $cursor->parameter($field->column());
        $ascending = $field->direction() === SortDirection::Ascending;

        $query->where(function (EloquentBuilder $query) use ($field, $rest, $operands, $cursor, $sql, $bindings, $value, $ascending): void {
            if ($value === null) {
                if (! $ascending) {
                    $query->whereRaw($sql.' is not null', $bindings);
                }
            } else {
                $query->whereRaw($sql.($ascending ? ' > ?' : ' < ?'), [...$bindings, $value]);

                if ($ascending && $field->isNullable()) {
                    $query->orWhereRaw($sql.' is null', $bindings);
                }
            }

            if ($rest !== []) {
                $query->orWhere(function (EloquentBuilder $query) use ($rest, $operands, $cursor, $sql, $bindings, $value): void {
                    $value === null
                        ? $query->whereRaw($sql.' is null', $bindings)
                        : $query->whereRaw($sql.' = ?', [...$bindings, $value]);

                    self::whereAfter($query, $rest, $operands, $cursor);
                });
            }
        });
    }
}
