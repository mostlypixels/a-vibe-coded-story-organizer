<?php

namespace App\Support;

use App\Enums\FieldKind;
use Illuminate\Support\Str;

/**
 * The one definition of "a word" in this app (word-count spec,
 * `.specs/planned/2026-07/word-count/expanded/architecture.md`). Pure text-in,
 * int-out — no model, no controller, no DB access (CLAUDE.md's "Where logic
 * lives": reference logic belongs in app/Support, beside {@see RichText}).
 *
 * Four steps, always in this order:
 *   1. Strip fenced code blocks from Markdown input, before rendering. A fence is
 *      marked as not-prose; inline code is left alone because it sits inside a
 *      sentence.
 *   2. Render the field's stored representation to plain text, by kind.
 *   3. Split on whitespace only — matches Word/Scrivener: `one—two` (no spaces) is
 *      one word, `jack-o'-lantern` is one word.
 *   4. Drop any token with no letter and no digit — removes `* * *` dividers, a
 *      lone `—`, and stray punctuation, while keeping `1,234` and `3.5`.
 */
class WordCounter
{
    /**
     * A fenced code block: an opening line of 3+ backticks or tildes (with an
     * optional info string, e.g. "```php"), its content, and a closing line of the
     * same fence character repeated exactly as many times. The backreference
     * (`\1`) is what stops a closing ``` from matching an opening ~~~ fence.
     */
    private const FENCE_PATTERN = '/^(`{3,}|~{3,})[^\n]*\n.*?\n\1[^\n]*$/ms';

    /**
     * Count the words in a stored field value. Null and whitespace-only input are
     * zero words, not an error — callers never need to special-case an empty field.
     */
    public static function count(?string $value, FieldKind $kind): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $text = match ($kind) {
            FieldKind::Rich => RichText::toPlainText($value),
            // Str::markdown() is what stops "**bold**" counting as one word and
            // "# " as another — render first, then reduce to plain text exactly
            // as RichText does for the Rich kind.
            FieldKind::Markdown => RichText::toPlainText(Str::markdown(self::stripFencedCodeBlocks($value))),
            FieldKind::Plain => $value,
        };

        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return 0;
        }

        return count(array_filter($tokens, static fn (string $token): bool => preg_match('/[\p{L}\p{N}]/u', $token) === 1));
    }

    /**
     * Remove fenced code blocks from raw Markdown before it is rendered. Done on
     * the source text, not on the rendered HTML: finding a stripped `<pre>` after
     * the fact would mean re-deriving what was already known here, more cheaply
     * and less fragilely.
     */
    private static function stripFencedCodeBlocks(string $markdown): string
    {
        return preg_replace(self::FENCE_PATTERN, '', $markdown) ?? $markdown;
    }
}
