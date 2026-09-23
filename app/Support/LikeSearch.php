<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A "contains" search that reads `%` and `_` in the user's text as plain characters.
 *
 * SQLite has no default LIKE escape character, so the query names one. The escape
 * is `!`, not a backslash, because MySQL string literals also read a backslash.
 */
class LikeSearch
{
    private const ESCAPE = '!';

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public static function whereContains(Builder $query, string $column, string $term): Builder
    {
        // Escape the escape character first, or it doubles the other escapes.
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );

        return $query->whereRaw(
            $query->getQuery()->getGrammar()->wrap($column).' like ? escape ?',
            ['%'.$escaped.'%', self::ESCAPE],
        );
    }
}
