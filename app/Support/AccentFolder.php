<?php

namespace App\Support;

use App\Services\ProjectSearch;

/**
 * Folds accented Latin characters to their plain lowercase ASCII base so search
 * can match across accents: a search for `Melusine` finds `Mélusine`, and the
 * reverse. This is the single source of truth for accent folding — both the SQL
 * side (via {@see sqlColumnExpression}) and the PHP side (via {@see fold}) derive
 * from the same {@see MAP}, so the database filter and the in-PHP re-check /
 * highlighting can never disagree about what "matches".
 *
 * Why a hand-rolled map instead of a DB collation or `Str::ascii()`:
 *   - The default (and production) database is SQLite, whose `LIKE`/`lower()`
 *     fold ASCII case only and never fold accents — and no `unaccent`/ICU
 *     collation is available. Relying on a collation would also give *different*
 *     results per engine (see documentation/architecture.md → Project search).
 *   - The folding must be byte-for-byte identical in SQL and PHP; the only way to
 *     guarantee that across four DB drivers is to build both from one explicit map.
 *
 * > [!IMPORTANT]
 * > The map is deliberately **1 character → 1 character**. Folding therefore never
 * > changes a string's character offsets, which is what lets {@see SearchSnippet}
 * > locate a match in the *folded* text and then slice/highlight the *original*
 * > (accented) text at the same offsets. Expanding ligatures (ß→ss, æ→ae, œ→oe)
 * > would break that invariant and are intentionally **out of scope** — a search
 * > for `coeur` will not match `cœur`. Keep every entry a single base letter.
 *
 * @see SearchSnippet sibling stateless helper that relies on the offset invariant
 * @see ProjectSearch the search service that folds both query and columns
 */
class AccentFolder
{
    /**
     * Accented character (both cases) => plain lowercase ASCII base letter.
     *
     * Covers the Western-European Latin set the app's content uses (French /
     * Italian, per MelusineSeeder). Every value is a single ASCII letter so the
     * 1:1-length invariant in the class docblock holds.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Ã' => 'a', 'Å' => 'a',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Õ' => 'o',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ç' => 'c', 'ç' => 'c',
        'Ñ' => 'n', 'ñ' => 'n',
        'Ý' => 'y', 'Ÿ' => 'y', 'ý' => 'y', 'ÿ' => 'y',
    ];

    /**
     * Fold a string for comparison: strip accents via {@see MAP}, then lowercase
     * the remaining ASCII.
     *
     * `strtr` replaces the multi-byte accented sequences from the map, and the
     * ASCII-only `strtolower` mirrors SQLite's ASCII-only `lower()` so this exactly
     * matches what {@see sqlColumnExpression} computes on the database side.
     */
    public static function fold(string $value): string
    {
        return strtolower(strtr($value, self::MAP));
    }

    /**
     * Build the portable SQL expression that folds a column the same way
     * {@see fold} folds a PHP string: a `lower(...)` wrapping a nested chain of
     * `replace(...)` calls, one per accent in {@see MAP}.
     *
     * Uses only ANSI `lower`/`replace`, so it runs identically on
     * sqlite/mysql/mariadb/pgsql/sqlsrv. The column name comes from
     * ProjectSearch's own constants (never user input) and the accent literals are
     * class constants, so embedding them raw is safe — the user's term stays a
     * bound parameter on the other side of the `LIKE`.
     *
     * @param  string  $column  a trusted column identifier (e.g. `name`)
     */
    public static function sqlColumnExpression(string $column): string
    {
        $expression = $column;

        foreach (self::MAP as $accented => $base) {
            $expression = "replace({$expression}, '{$accented}', '{$base}')";
        }

        return "lower({$expression})";
    }
}
