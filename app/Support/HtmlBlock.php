<?php

namespace App\Support;

use App\Services\Diff\HtmlTokenizer;

/**
 * One block of rich text — a paragraph, heading, list item, quote, code block,
 * table row, rule or image — as {@see HtmlTokenizer} sees it.
 *
 * The visual differ works at two levels, and this value object is the currency
 * of the outer one: blocks are matched against each other first (by
 * {@see self::matchKey()}), and only a *matched pair* gets its {@see self::$tokens}
 * compared word by word.
 *
 * Three fields answer three different questions, which is why none of them can
 * be derived from another:
 *
 * * `text` — what the block says, whitespace normalised. Used for display and
 *   for judging "same content?".
 * * `signature` — how it says it: a fingerprint of where the inline marks start
 *   and stop. Two blocks with the same `text` and a different `signature` are a
 *   *formatting-only* change, which the UI labels rather than diffing.
 * * `tokens` — the word stream, each word carrying its marks, for the inline diff.
 */
final readonly class HtmlBlock
{
    /**
     * Sits between two table cells in {@see self::$tokens} — a record separator
     * (U+001E), so no real word can collide with it. The renderer splits a row's
     * tokens back into cells on it.
     */
    public const CELL_BOUNDARY = "\u{001E}";

    /**
     * How the same boundary reads inside {@see self::$text}, which is meant to
     * stay human-readable.
     */
    public const CELL_SEPARATOR = ' | ';

    /**
     * Separates the parts of {@see self::matchKey()}.
     */
    private const KEY_SEPARATOR = "\u{001F}";

    /**
     * @param  string  $tag  One of {@see HtmlTokenizer::BLOCK_TAGS}.
     * @param  array<string, string|int>  $attributes  Only what the renderer needs: `src`/`alt`
     *                                                 for an image, `data-checked` for a task
     *                                                 item, `data-callout-type` for a quote,
     *                                                 plus the structural `list-type`/`depth`
     *                                                 of a list item.
     * @param  list<InlineToken>  $tokens
     */
    public function __construct(
        public string $tag,
        public array $attributes,
        public string $text,
        public array $tokens,
        public string $signature,
    ) {}

    /**
     * The string the block-level sequence matcher compares blocks by.
     *
     * Deliberately more than `text`: a paragraph that became a heading, a list
     * item that changed nesting depth, and a swapped image all read as "the
     * same text" while being genuinely different blocks. Marks are *not* part of
     * it — a formatting-only change must still match, so that the differ can
     * recognise it as such instead of reporting a delete plus an insert.
     */
    public function matchKey(): string
    {
        $structural = array_intersect_key($this->attributes, array_flip(['list-type', 'depth', 'src']));

        return implode(self::KEY_SEPARATOR, [$this->tag, $this->text, json_encode($structural)]);
    }

    /**
     * Whether this block carries no text of its own — an `hr`, or an image with
     * no alt text. Such blocks are matched structurally (see {@see self::matchKey()})
     * rather than by their empty text.
     */
    public function isVoid(): bool
    {
        return $this->tokens === [];
    }
}
