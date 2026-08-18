<?php

namespace App\Support;

/**
 * Builds an accent-insensitive highlighted search excerpt.
 *
 * It escapes all source text and emits only its own `<mark>` elements.
 */
class SearchSnippet
{
    /** Approximate context length around the first match. */
    public const CONTEXT_LENGTH = 120;

    /** Theme roles for the generated mark element. */
    private const HIGHLIGHT_CLASS = 'bg-highlight text-highlight-content';

    private const ELLIPSIS = "\u{2026}";

    /** @param string|array<int, string> $terms */
    public static function highlight(string $text, string|array $terms, int $length = self::CONTEXT_LENGTH): string
    {
        $terms = self::normalizeTerms($terms);

        if ($terms === []) {
            return self::escapeWindow($text, 0, $length);
        }

        $firstMatch = self::firstMatchOffset($text, $terms);

        if ($firstMatch === null) {
            return self::escapeWindow($text, 0, $length);
        }

        [$windowText, $prefix, $suffix] = self::window($text, $firstMatch, $length);

        return $prefix.self::highlightWindow($windowText, $terms).$suffix;
    }

    /**
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
     * Returns the earliest folded match offset. Folding preserves character offsets.
     *
     * @param  array<int, string>  $terms
     */
    private static function firstMatchOffset(string $text, array $terms): ?int
    {
        $foldedText = AccentFolder::fold($text);
        $earliest = null;

        foreach ($terms as $term) {
            $offset = mb_strpos($foldedText, AccentFolder::fold($term));

            if ($offset !== false && ($earliest === null || $offset < $earliest)) {
                $earliest = $offset;
            }
        }

        return $earliest;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function window(string $text, int $matchOffset, int $length): array
    {
        $textLength = mb_strlen($text);

        $start = max(0, $matchOffset - intdiv($length, 2));
        $end = min($textLength, $start + $length);
        $start = max(0, $end - $length);

        $windowText = mb_substr($text, $start, $end - $start);
        $prefix = $start > 0 ? self::ELLIPSIS : '';
        $suffix = $end < $textLength ? self::ELLIPSIS : '';

        return [$windowText, $prefix, $suffix];
    }

    /** Escapes an excerpt without highlighting. */
    private static function escapeWindow(string $text, int $matchOffset, int $length): string
    {
        [$windowText, $prefix, $suffix] = self::window($text, $matchOffset, $length);

        return $prefix.e($windowText).$suffix;
    }

    /**
     * Finds matches in folded text but emits escaped slices from the original text.
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

        $pattern = '/('.$alternation.')/iu';

        // Convert regex byte offsets to character offsets before slicing.
        if (preg_match_all($pattern, $foldedWindow, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return e($windowText);
        }

        $html = '';
        $cursor = 0; // character cursor into the original window

        foreach ($matches[0] as [$matchText, $byteOffset]) {
            $charOffset = mb_strlen(substr($foldedWindow, 0, $byteOffset));
            $charLength = mb_strlen($matchText);

            if ($charOffset > $cursor) {
                $html .= e(mb_substr($windowText, $cursor, $charOffset - $cursor));
            }

            $html .= '<mark class="'.self::HIGHLIGHT_CLASS.'">'
                .e(mb_substr($windowText, $charOffset, $charLength)).'</mark>';

            $cursor = $charOffset + $charLength;
        }

        if ($cursor < mb_strlen($windowText)) {
            $html .= e(mb_substr($windowText, $cursor));
        }

        return $html;
    }
}
