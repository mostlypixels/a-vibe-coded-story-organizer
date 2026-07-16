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
     * Character offset of the earliest case-insensitive occurrence of any term,
     * or null if none of the terms appear in the text.
     *
     * @param  array<int, string>  $terms
     */
    private static function firstMatchOffset(string $text, array $terms): ?int
    {
        $earliest = null;

        foreach ($terms as $term) {
            $offset = mb_stripos($text, $term);

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
     * Escape the window text and wrap every term occurrence in a <mark>.
     *
     * Splitting on the term pattern (with the captured delimiter kept) yields
     * alternating [plain, match, plain, match, ...] segments; each segment is
     * escaped individually, so raw HTML in the text can never become live markup
     * and the only tags in the output are the <mark> wrappers we add ourselves.
     *
     * @param  array<int, string>  $terms
     */
    private static function highlightWindow(string $windowText, array $terms): string
    {
        $alternation = implode('|', array_map(
            static fn (string $term) => preg_quote($term, '/'),
            $terms
        ));

        $pattern = '/('.$alternation.')/iu';

        $segments = preg_split($pattern, $windowText, -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            // Odd indices are the captured (matched) delimiters.
            if ($index % 2 === 1) {
                $html .= '<mark class="'.self::HIGHLIGHT_CLASS.'">'.e($segment).'</mark>';
            } else {
                $html .= e($segment);
            }
        }

        return $html;
    }
}
