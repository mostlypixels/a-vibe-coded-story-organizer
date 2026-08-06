<?php

namespace App\Support;

/**
 * The one place the "N words" translation key lives.
 * `x-word-count` (resources/views/components/word-count.blade.php) renders the
 * final formatted string from {@see self::text()}; the live in-field counter
 * (resources/js/word-count.js) needs the same three pluralised forms *without*
 * a number baked in yet, so it can fill in whatever it just counted client-side
 * without inventing its own copy of this string (or of English pluralisation
 * rules) — that is what {@see self::jsTemplates()} is for.
 *
 * Both call `trans_choice()` with the same translation key, so a future wording
 * change is made once and both the static count and the live counter pick it up.
 * This app has no lang/ files, so the inline pluralisation string below doubles
 * as the translation key. Every other `trans_choice` call in the codebase does
 * the same.
 */
class WordCountFormat
{
    private const TRANSLATION_KEY = '{0} :count words|{1} :count word|[2,*] :count words';

    /** The final, formatted string — "1,234 words" / "1 word" / "0 words". */
    public static function text(int $count): string
    {
        return trans_choice(self::TRANSLATION_KEY, $count, ['count' => number_format($count)]);
    }

    /**
     * The three pluralised branches (zero/one/other), each still carrying a
     * literal `%d` in place of the count. `trans_choice()` always substitutes
     * `:count` unless the caller's own `$replace` array already sets it
     * (`Translator::choice()`: `if (! isset($replace['count']))`) — passing
     * `'%d'` there is what keeps the branch a template instead of a filled-in
     * string, so resources/js/word-count.js can drop the actual typed count
     * into it without ever hardcoding "word"/"words" or a thousands separator.
     *
     * @return array{zero: string, one: string, other: string}
     */
    public static function jsTemplates(): array
    {
        return [
            'zero' => trans_choice(self::TRANSLATION_KEY, 0, ['count' => '%d']),
            'one' => trans_choice(self::TRANSLATION_KEY, 1, ['count' => '%d']),
            'other' => trans_choice(self::TRANSLATION_KEY, 2, ['count' => '%d']),
        ];
    }
}
