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
    public const ESCAPE = '!';

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public static function whereContains(Builder $query, string $column, string $term): Builder
    {
        return $query->whereRaw(
            $query->getQuery()->getGrammar()->wrap($column).' like ? escape ?',
            ['%'.self::escape($term).'%', self::ESCAPE],
        );
    }

    /**
     * A "contains" pattern that finds at least every value where the folded term
     * is in the folded value ({@see AccentFolder::fold()}). PHP then checks each
     * candidate, so extra candidates cost time but never change a result.
     *
     * A letter with accented forms becomes `_`, because SQLite LIKE does not fold
     * accents. LIKE already ignores ASCII case. Use the pattern with {@see ESCAPE}.
     */
    public static function accentInsensitivePattern(string $term): string
    {
        $pattern = '';

        foreach (mb_str_split($term) as $character) {
            $pattern .= AccentFolder::hasAccentedForms($character) ? '_' : self::escape($character);
        }

        return '%'.$pattern.'%';
    }

    private static function escape(string $term): string
    {
        // Escape the escape character first, or it doubles the other escapes.
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );
    }
}
