<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * Names the database of every table in a query of another connection, so the
 * query runs as a subquery of the list's connection. Laravel prefixes only the
 * outermost table of a subquery (`Query\Builder::parseSub()`), and a query
 * built for a correlated subquery or a `whereHas()` not even that.
 *
 * That needs both databases on one server, readable through the list's
 * connection: MySQL or MariaDB in practice.
 *
 * @internal used by the token search and the relation sort
 */
final class CrossDatabaseTables
{
    /**
     * Prefixes the table and the joined tables of the query, and of every
     * subquery in its where clauses (`whereHas()`, nested wheres), with their
     * database name where it is not the list's.
     *
     * SQL that is a string already, a raw where or a `whereIn` subquery in a
     * scope of the related model, is out of reach.
     */
    public static function qualify(QueryBuilder $query, string $listDatabase): void
    {
        $database = $query->getConnection()->getDatabaseName();

        if ($database !== $listDatabase) {
            if (is_string($query->from)) {
                $query->from = self::qualifyTable($query->from, $database);
            }

            foreach ($query->joins ?? [] as $join) {
                if ($join instanceof JoinClause && is_string($join->table)) {
                    $join->table = self::qualifyTable($join->table, $database);
                }
            }
        }

        foreach ($query->wheres as $where) {
            if (($where['query'] ?? null) instanceof QueryBuilder) {
                self::qualify($where['query'], $listDatabase);
            }
        }
    }

    /**
     * A table that names its database (or schema) already is left alone.
     */
    private static function qualifyTable(string $table, string $database): string
    {
        return str_contains($table, '.') ? $table : $database.'.'.$table;
    }
}
