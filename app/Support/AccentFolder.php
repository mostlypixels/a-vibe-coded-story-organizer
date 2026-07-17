<?php

namespace App\Support;

use App\Services\ProjectSearch;

/**
 * Folds accented Latin characters to their plain lowercase ASCII base so search
 * can match across accents: a search for `Melusine` finds `Mélusine`, and the
 * reverse. This is the single source of truth for accent folding used by every
 * part of search that must agree on what "matches" — {@see ProjectSearch}
 * (both the entity gate and the per-field label check) and {@see SearchSnippet}
 * (offset + highlight).
 *
 * Why a hand-rolled map instead of a DB collation, `Str::ascii()`, or an SQL
 * expression:
 *   - Matching is done in PHP, not SQL, so it is identical and portable across
 *     every driver. The default (and production) database is SQLite, whose
 *     `LIKE`/`lower()` fold ASCII case only, never accents, with no `unaccent`/ICU
 *     collation; and a folding SQL expression (a deep nested-REPLACE chain)
 *     overflows SQLite's parser on some builds. See documentation/architecture.md
 *     → Project search.
 *   - A fixed, explicit map keeps folding deterministic and identical everywhere
 *     it is applied (gate, label, snippet).
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
     * the remaining ASCII. `strtr` replaces the multi-byte accented sequences from
     * the map; the result is a plain, lowercase, accent-free string suitable for a
     * literal `str_contains` match.
     */
    public static function fold(string $value): string
    {
        return strtolower(strtr($value, self::MAP));
    }
}
