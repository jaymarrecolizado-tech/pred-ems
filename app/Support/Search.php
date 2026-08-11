<?php

namespace App\Support;

/**
 * Escapes SQL LIKE wildcards in user-supplied search input so that
 * literal `%`, `_`, and `\` characters in the query are treated as
 * ordinary characters, not as wildcard patterns.
 *
 * Without this, a user searching for "100%" would match everything
 * (wildcard injection).
 */
class Search
{
    /**
     * Escape a search string for safe use inside a SQL LIKE clause.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }

    /**
     * Build a parameterized LIKE pattern: %escaped_value%.
     */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }
}
