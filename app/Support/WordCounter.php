<?php

namespace App\Support;

use App\Enums\FieldKind;

/**
 * The one definition of "a word" in this app (word-count spec,
 * `.specs/shipped/2026-07/word-count/expanded/architecture.md`). Pure text-in,
 * int-out, with no model or database access.
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
     * A closed fenced code block: an opening line of 3+ backticks or tildes (with
     * an optional info string, e.g. "```php"), its content, and a closing line of
     * the same character, at least as long.
     *
     * Group 2 is the single fence character and group 1 the whole opening run, so
     * `\1\2*` means "the opening fence, possibly longer" — CommonMark lets ````
     * close ```, but never lets ~~~ close ```. Both lines may be indented up to
     * three spaces and still be a fence — at four CommonMark stops seeing a fence
     * at all and the line is ordinary indented code, which this feature counts.
     */
    private const CLOSED_FENCE_PATTERN = '/^ {0,3}((`|~)\2{2,})[^\n]*\n(?:.*?\n)? {0,3}\1\2*[ \t]*$/ms';

    /**
     * An opening fence with no closing one. CommonMark treats the rest of the
     * document as code, so this strips to the end of the input. Applied only after
     * {@see CLOSED_FENCE_PATTERN} has run, so anything still matching here really
     * is unclosed.
     */
    private const UNCLOSED_FENCE_PATTERN = '/^ {0,3}((`|~)\2{2,})[^\n]*(?:\n.*)?\z/ms';

    /**
     * Count the words in a stored field value. Null and whitespace-only input are
     * zero words, not an error — callers never need to special-case an empty field.
     */
    public static function count(?string $value, FieldKind $kind): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        // A malformed byte sequence can reach a stored field (Scene.contents has
        // no sanitizing mutator — see Scene::renderedContents()'s docblock) from
        // a writer's paste or an old import. The Markdown renderer throws on anything
        // that is not valid UTF-8/ASCII; degrading to zero rather than letting
        // that propagate into a 500 on save matches how SceneReferenceMatcher
        // already treats the same failure mode for the same column.
        if (! mb_check_encoding($value, 'UTF-8')) {
            return 0;
        }

        $text = match ($kind) {
            FieldKind::Rich => RichText::toPlainText($value),
            // Rendering is what stops "**bold**" counting as one word and
            // "# " as another — render first, then reduce to plain text exactly
            // as RichText does for the Rich kind.
            FieldKind::Markdown => RichText::toPlainText(AuthorMarkdown::renderUnsanitized(self::stripFencedCodeBlocks($value))),
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
     *
     * Closed fences go first so that what remains matching an *opening* fence is
     * genuinely unclosed. Reversing the order would let an unclosed-fence match
     * swallow the rest of a document whose fences were all closed.
     */
    private static function stripFencedCodeBlocks(string $markdown): string
    {
        $withoutClosedFences = preg_replace(self::CLOSED_FENCE_PATTERN, '', $markdown) ?? $markdown;

        return preg_replace(self::UNCLOSED_FENCE_PATTERN, '', $withoutClosedFences) ?? $withoutClosedFences;
    }
}
