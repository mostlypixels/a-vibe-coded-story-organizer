<?php

namespace App\Services;

/**
 * Finds literal matches of a find text in raw scene Markdown, for find and replace.
 *
 * Always accent-sensitive: an accent-blind replace would overwrite accents. Word edges
 * use letter/number lookarounds, not `\b`, because `\b` fails next to accented letters.
 */
class SceneTextMatcher
{
    /**
     * @return list<array{offset: int, length: int}> byte offsets, left to right, no overlaps
     */
    public function matches(string $text, string $find, bool $matchCase, bool $wholeWord): array
    {
        if ($find === '' || ! mb_check_encoding($text, 'UTF-8') || ! mb_check_encoding($find, 'UTF-8')) {
            return [];
        }

        $pattern = preg_quote($find, '/');

        if ($wholeWord) {
            $pattern = '(?<![\p{L}\p{N}_])'.$pattern.'(?![\p{L}\p{N}_])';
        }

        $flags = $matchCase ? 'u' : 'ui';

        if (preg_match_all('/'.$pattern.'/'.$flags, $text, $found, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        return array_map(
            fn (array $match): array => ['offset' => $match[1], 'length' => strlen($match[0])],
            $found[0],
        );
    }
}
