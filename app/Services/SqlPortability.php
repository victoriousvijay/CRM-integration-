<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Small helpers for SQL that must run identically on MySQL, PostgreSQL
 * (Supabase) and SQLite (local/tests) — mainly date-arithmetic, since none
 * of the three databases agree on a single syntax for "days between two
 * timestamps".
 */
class SqlPortability
{
    /**
     * A `DB::raw` expression computing whole/fractional days between
     * $laterCol and $earlierCol (later - earlier), aliased as $alias.
     * $earlierCol/$laterCol may be the literal string 'NOW()'.
     *
     * Pass $aggregate ('avg', 'min', 'max', 'sum', ...) to wrap the
     * difference in that SQL aggregate function before aliasing.
     */
    public static function dateDiffDaysExpr(string $laterCol, string $earlierCol, string $alias, ?string $aggregate = null)
    {
        $inner = match (DB::getDriverName()) {
            'sqlite' => "julianday(".self::sqliteArg($laterCol).") - julianday(".self::sqliteArg($earlierCol).")",
            'pgsql' => "EXTRACT(EPOCH FROM ({$laterCol} - {$earlierCol})) / 86400",
            default => "DATEDIFF({$laterCol}, {$earlierCol})", // mysql / mariadb
        };

        $expr = $aggregate ? "{$aggregate}({$inner})" : $inner;

        return DB::raw("{$expr} as {$alias}");
    }

    private static function sqliteArg(string $value): string
    {
        return $value === 'NOW()' ? "'now'" : $value;
    }
}
