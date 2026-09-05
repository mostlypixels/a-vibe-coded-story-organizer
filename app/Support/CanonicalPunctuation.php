<?php

namespace App\Support;

use DOMDocument;
use DOMProcessingInstruction;
use DOMXPath;

/**
 * Converts typewriter punctuation to the typographic punctuation the app stores.
 * This class is the single source of truth for that convention: `--` and `---`
 * become dashes, `...` becomes an ellipsis, and straight quotes curl.
 *
 * The definition itself lives in `tests/Fixtures/punctuation.json`. Three
 * implementations must agree with that file: this class (import), and the two
 * editor paths in `resources/js/wysiwyg.js` (typing and paste).
 *
 * Why hand-rolled instead of `league/commonmark`'s SmartPunct extension:
 *   - SmartPunct writes HTML. Scene contents are stored as Markdown source, and
 *     CommonMark has no Markdown writer, so it cannot be used to normalize text
 *     in place. SmartPunct stays the *oracle* the fixture is checked against
 *     (`tests/Unit/Support/PunctuationFixtureTest.php`), not the implementation.
 *   - The editor needs the same convention in JavaScript. A hand-rolled rule set
 *     small enough to state in one sentence can be repeated there; a CommonMark
 *     delimiter-run implementation cannot.
 *
 * > [!IMPORTANT]
 * > The method is idempotent: normalized text passed back in comes out unchanged.
 * > Import may run over already-canonical text (a re-imported export), so every
 * > rule must only ever consume ASCII input characters.
 *
 * @see AccentFolder sibling stateless text helper
 */
class CanonicalPunctuation
{
    /**
     * Characters that may stand before an opening quote. Start-of-run and
     * whitespace are the main cases; the brackets and dashes are here because a
     * quote after them opens too — `("Hello")` must curl outward.
     */
    private const OPENERS = ['(', '[', '{', '<', '–', '—', '-', '/'];

    /**
     * Normalize one run of prose. The input must carry no markup and no code:
     * callers are responsible for shredding Markdown or HTML first, because this
     * method cannot tell a quote in a sentence from a quote in a code span.
     */
    public static function inPlainText(string $text): string
    {
        $text = str_replace(['---', '--', '...'], ['—', '–', '…'], $text);

        return self::curlQuotes($text);
    }

    /**
     * Normalize the prose in a Markdown document and return every code construct
     * byte-identical: fenced blocks (``` and ~~~), indented code blocks, and
     * backtick code spans.
     *
     * The document is read line by line rather than parsed. CommonMark has no
     * Markdown writer, so a round-trip through the parser cannot give the author's
     * source back; a shredder that only has to find code fences can.
     *
     * > [!NOTE]
     * > A line indented by four spaces counts as code only after a blank line, the
     * > CommonMark condition. This still misreads an indented continuation line of
     * > a list item as code, which leaves that prose un-normalized. Under-reach is
     * > the safe direction: it never damages code.
     */
    public static function inMarkdown(string $markdown): string
    {
        // DELIM_CAPTURE keeps the line endings, so the document is rebuilt exactly.
        $parts = preg_split('/(\R)/u', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $fence = null;
        $inIndentedCode = false;
        $afterBlankLine = true;

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $result .= $part;

                continue;
            }

            if ($fence !== null) {
                $result .= $part;
                if (preg_match('/^ {0,3}'.preg_quote($fence, '/').'{3,}\s*$/u', $part) === 1) {
                    $fence = null;
                }

                continue;
            }

            if (preg_match('/^ {0,3}(`|~)\1{2,}/u', $part, $matches) === 1) {
                $fence = $matches[1];
                $result .= $part;
                $inIndentedCode = false;
                $afterBlankLine = false;

                continue;
            }

            $isBlank = trim($part) === '';
            $isIndented = preg_match('/^(?: {4}|\t)/u', $part) === 1;

            if ($isIndented && ($inIndentedCode || $afterBlankLine)) {
                $inIndentedCode = true;
                $result .= $part;
                $afterBlankLine = false;

                continue;
            }

            if (! $isBlank) {
                $inIndentedCode = false;
            }

            $result .= self::outsideCodeSpans($part);
            $afterBlankLine = $isBlank;
        }

        return $result;
    }

    /**
     * Normalize the prose in an HTML fragment, leaving the markup alone: tags,
     * attribute values and anything inside `<pre>` or `<code>` come back unchanged.
     *
     * The DOM round-trip (parse, walk text nodes, re-serialize) is the one already
     * used by {@see RichText::toXhtmlFragment()}, including the XML processing
     * instruction that forces the HTML parser to read the bytes as UTF-8.
     * Attribute values are not text nodes, so the walk cannot reach them.
     */
    public static function inHtml(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        $document->loadHTML(
            '<?xml encoding="UTF-8"?>'.$html,
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $textNodes = (new DOMXPath($document))
            ->query('//text()[not(ancestor::pre) and not(ancestor::code)]');

        if ($textNodes !== false) {
            foreach ($textNodes as $node) {
                $node->nodeValue = self::inPlainText($node->nodeValue ?? '');
            }
        }

        $fragment = '';
        foreach ($document->childNodes as $child) {
            if ($child instanceof DOMProcessingInstruction) {
                continue;
            }

            $fragment .= $document->saveHTML($child);
        }

        return $fragment;
    }

    /**
     * Send each stretch of a Markdown line that is not a backtick code span through
     * {@see inPlainText()}. The span pattern matches a run of backticks and the
     * same-length run that closes it, which is how CommonMark delimits a code span.
     */
    private static function outsideCodeSpans(string $line): string
    {
        $pattern = '/(?<!`)(`+)(?!`)(?:[\s\S]*?)(?<!`)\1(?!`)/u';
        $result = '';
        $offset = 0;

        while (preg_match($pattern, $line, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            [$span, $start] = $matches[0];
            $result .= self::inPlainText(substr($line, $offset, $start - $offset)).$span;
            $offset = $start + strlen($span);
        }

        return $result.self::inPlainText(substr($line, $offset));
    }

    /**
     * Curl every straight quote in the run.
     *
     * A quote opens when the character before it is start-of-run, whitespace or
     * an opening bracket, **and** the character after it is not a digit. A digit
     * after means an elision (`the '90s`), which is a closing mark. Otherwise the
     * quote closes, which also covers an apostrophe inside a word (`don't`).
     *
     * This is a simplification of CommonMark's left/right-flanking delimiter run.
     * The fixture is what proves it close enough.
     */
    private static function curlQuotes(string $text): string
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = '';

        foreach ($characters as $index => $character) {
            if ($character !== "'" && $character !== '"') {
                $result .= $character;

                continue;
            }

            $before = $characters[$index - 1] ?? '';
            $after = $characters[$index + 1] ?? '';

            $opens = ($before === '' || preg_match('/\s/u', $before) === 1 || in_array($before, self::OPENERS, true))
                && preg_match('/\d/u', $after) !== 1;

            $result .= match (true) {
                $character === "'" => $opens ? '‘' : '’',
                default => $opens ? '“' : '”',
            };
        }

        return $result;
    }
}
