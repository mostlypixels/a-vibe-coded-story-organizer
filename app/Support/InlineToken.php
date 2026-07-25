<?php

namespace App\Support;

use App\Services\Diff\HtmlTokenizer;

/**
 * One word of rich text, plus the inline marks in force at that word.
 *
 * The unit the *inline* half of the visual diff compares. A "mark" is an inline
 * formatting element wrapping the word — `strong`, `em`, `u`, `s`, `code`, or a
 * link (recorded as `a:<href>`, so re-pointing a link is a change even when the
 * text is identical).
 *
 * The whole point of carrying the marks on the token is {@see self::comparable()}:
 * because a token compares as a single string that folds the marks in, a plain
 * sequence matcher sees "the word was bolded" as a change with no special-casing
 * anywhere in the differ.
 */
final readonly class InlineToken
{
    /**
     * Separates the mark stack from the word inside {@see self::comparable()}.
     * A unit separator (U+001F) rather than a printable character, so no real
     * word can ever collide with it.
     */
    public const SEPARATOR = "\u{001F}";

    /**
     * @param  string  $word  A whitespace-delimited word, or {@see HtmlBlock::CELL_BOUNDARY}.
     * @param  list<string>  $marks  Outermost first, e.g. `['strong', 'a:https://x.test']`.
     */
    public function __construct(
        public string $word,
        public array $marks = [],
    ) {}

    /**
     * The single string a sequence matcher compares this token by.
     *
     * @see HtmlTokenizer for the parsing side of this contract.
     */
    public function comparable(): string
    {
        return implode(',', $this->marks).self::SEPARATOR.$this->word;
    }

    /**
     * Whether this token is a table-cell boundary rather than real text.
     */
    public function isCellBoundary(): bool
    {
        return $this->word === HtmlBlock::CELL_BOUNDARY;
    }
}
