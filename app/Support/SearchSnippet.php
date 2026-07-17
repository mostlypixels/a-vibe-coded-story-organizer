<?php

namespace App\Support;

/**
 * Builds the highlighted, HTML-safe snippet shown for a single search match.
 *
 * The search results page needs to show a short excerpt of the matching field
 * with the matched term(s) highlighted. Because the source text is arbitrary
 * user input — `Scene::contents` (raw Markdown) and `Scene::notes` (rich HTML
 * source) can contain `<`, `&`, even literal `<script>` — the snippet MUST be
 * escaped before it reaches the view. This helper escapes first and only then
 * injects the `<mark>` wrapper, so the returned string is safe for the single
 * deliberate `{!! !!}` in the search view.
 *
 * It is intentionally mode-agnostic: it accepts the term(s) to highlight as a
 * string or an array of strings and does not care how AllTerms / AnyTerm /
 * ExactPhrase produced them (see task 02's ProjectSearch service).
 *
 * @see PlotlineColors sibling stateless helper in this namespace
 */
class SearchSnippet
{
    /**
     * Approximate number of characters of context in a snippet, centered on the
     * first match. The `sun` palette highlight shade (`bg-sun-200` = #ffe494) is
     * a deliberate choice — the palette has no `200` shade and `bg-sun-400` is
     * already the table-header color.
     */
    public const CONTEXT_LENGTH = 120;

    private const HIGHLIGHT_CLASS = 'bg-sun-200';

    private const ELLIPSIS = "\u{2026}";

    /**
     * Return pre-escaped HTML: ~120 characters of context centered on the first
     * case-insensitive match, with every occurrence of the term(s) wrapped in
     * `<mark class="bg-sun-200">`. All non-mark text is HTML-escaped.
     *
     * @param  string|array<int, string>  $terms  the term(s) to highlight
     */
    public static function highlight(string $text, string|array $terms, int $length = self::CONTEXT_LENGTH): string
    {
        $terms = self::normalizeTerms($terms);

        // No usable terms: just show an escaped excerpt from the start.
        if ($terms === []) {
            return self::escapeWindow($text, 0, $length);
        }

        $firstMatch = self::firstMatchOffset($text, $terms);

        // No term actually appears: fall back to an escaped leading excerpt.
        if ($firstMatch === null) {
            return self::escapeWindow($text, 0, $length);
        }

        [$windowText, $prefix, $suffix] = self::window($text, $firstMatch, $length);

        return $prefix.self::highlightWindow($windowText, $terms).$suffix;
    }

    /**
     * Drop empty/whitespace-only terms and re-index. Everything is cast to string.
     *
     * @param  string|array<int, string>  $terms
     * @return array<int, string>
     */
    private static function normalizeTerms(string|array $terms): array
    {
        $terms = array_map('strval', (array) $terms);
        $terms = array_filter($terms, static fn (string $term) => trim($term) !== '');

        return array_values($terms);
    }

    /**
     * Character offset of the earliest case- and accent-insensitive occurrence of
     * any term, or null if none of the terms appear in the text.
     *
     * The search runs on the accent-folded text, but because {@see AccentFolder}
     * folds 1 character → 1 character the returned offset is equally valid in the
     * original text (see {@see AccentFolder}'s offset invariant).
     *
     * @param  array<int, string>  $terms
     */
    private static function firstMatchOffset(string $text, array $terms): ?int
    {
        $foldedText = AccentFolder::fold($text);
        $earliest = null;

        foreach ($terms as $term) {
            // fold() already lowercases, so mb_strpos is the right case-insensitive
            // primitive here (no _i variant needed).
            $offset = mb_strpos($foldedText, AccentFolder::fold($term));

            if ($offset !== false && ($earliest === null || $offset < $earliest)) {
                $earliest = $offset;
            }
        }

        return $earliest;
    }

    /**
     * Slice a ~$length-character window centered on $matchOffset. Returns the raw
     * (still-unescaped) window text plus the leading/trailing ellipsis markers.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function window(string $text, int $matchOffset, int $length): array
    {
        $textLength = mb_strlen($text);

        $start = max(0, $matchOffset - intdiv($length, 2));
        $end = min($textLength, $start + $length);
        // If we clipped the end, pull the start back so the window stays ~$length.
        $start = max(0, $end - $length);

        $windowText = mb_substr($text, $start, $end - $start);
        $prefix = $start > 0 ? self::ELLIPSIS : '';
        $suffix = $end < $textLength ? self::ELLIPSIS : '';

        return [$windowText, $prefix, $suffix];
    }

    /**
     * Escape an excerpt (no highlighting) — used when there is nothing to mark.
     */
    private static function escapeWindow(string $text, int $matchOffset, int $length): string
    {
        [$windowText, $prefix, $suffix] = self::window($text, $matchOffset, $length);

        return $prefix.e($windowText).$suffix;
    }

    /**
     * Escape the window text and wrap every (accent-insensitive) term occurrence
     * in a <mark>.
     *
     * Matches are located in the accent-*folded* window (so `Melusine` highlights
     * `Mélusine`) but the emitted text is always sliced from the *original* window,
     * so the reader still sees the accented characters. This relies on
     * {@see AccentFolder} folding 1 character → 1 character: a match's character
     * offset and length in the folded window are identical in the original one.
     *
     * Every emitted slice — plain or matched — is escaped individually, so raw HTML
     * in the text can never become live markup; the only tags in the output are the
     * <mark> wrappers we add ourselves.
     *
     * @param  array<int, string>  $terms
     */
    private static function highlightWindow(string $windowText, array $terms): string
    {
        $foldedWindow = AccentFolder::fold($windowText);

        $alternation = implode('|', array_map(
            static fn (string $term) => preg_quote(AccentFolder::fold($term), '/'),
            $terms
        ));

        // fold() already lowercased both sides, so /i is only belt-and-braces.
        $pattern = '/('.$alternation.')/iu';

        // PREG_OFFSET_CAPTURE reports BYTE offsets into the folded window; we
        // convert each to a CHARACTER offset (valid in both windows) before slicing.
        if (preg_match_all($pattern, $foldedWindow, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return e($windowText);
        }

        $html = '';
        $cursor = 0; // character cursor into the original window

        foreach ($matches[0] as [$matchText, $byteOffset]) {
            $charOffset = mb_strlen(substr($foldedWindow, 0, $byteOffset));
            $charLength = mb_strlen($matchText);

            // Plain text before this match (taken from the original window).
            if ($charOffset > $cursor) {
                $html .= e(mb_substr($windowText, $cursor, $charOffset - $cursor));
            }

            // The matched slice, taken from the ORIGINAL so accents still render.
            $html .= '<mark class="'.self::HIGHLIGHT_CLASS.'">'
                .e(mb_substr($windowText, $charOffset, $charLength)).'</mark>';

            $cursor = $charOffset + $charLength;
        }

        // Trailing plain text after the last match.
        if ($cursor < mb_strlen($windowText)) {
            $html .= e(mb_substr($windowText, $cursor));
        }

        return $html;
    }
}
