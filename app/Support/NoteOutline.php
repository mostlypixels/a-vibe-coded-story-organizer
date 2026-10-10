<?php

namespace App\Support;

/**
 * The headings of a note body and the body with an `id` on each of them.
 *
 * Anchors are added when the page renders. The stored HTML and the sanitizer
 * allow-list stay as they are. Only the opening heading tags change, so the rest
 * of the markup is byte for byte the same.
 */
final readonly class NoteOutline
{
    /** The Contents card shows from this many headings. */
    public const MIN_HEADINGS = 3;

    /**
     * @param  list<array{level: int, text: string, anchor: string}>  $headings
     */
    private function __construct(
        public array $headings,
        public string $html,
    ) {}

    public static function from(?string $html): self
    {
        $html ??= '';
        $headings = [];
        $used = [];

        $withAnchors = preg_replace_callback(
            '#<h([1-4])((?:\s[^>]*)?)>(.*?)</h\1\s*>#is',
            function (array $match) use (&$headings, &$used): string {
                $level = (int) $match[1];
                $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5)));
                $anchor = self::uniqueAnchor($text, $used);

                $headings[] = ['level' => $level, 'text' => $text, 'anchor' => $anchor];

                // An id from outside would clash with ours.
                $attributes = (string) preg_replace('/\sid\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[2]);

                return "<h{$level}{$attributes} id=\"{$anchor}\">{$match[3]}</h{$level}>";
            },
            $html,
        );

        return new self($headings, $withAnchors ?? $html);
    }

    public function hasContents(): bool
    {
        return count($this->headings) >= self::MIN_HEADINGS;
    }

    /** Lowest heading level in the note: the indent of the list counts from it. */
    public function topLevel(): int
    {
        return $this->headings === [] ? 1 : min(array_column($this->headings, 'level'));
    }

    /** @param  array<string, true>  $used */
    private static function uniqueAnchor(string $text, array &$used): string
    {
        $slug = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)), '-');
        $base = $slug === '' ? 'section' : $slug;

        $anchor = $base;
        for ($suffix = 2; isset($used[$anchor]); $suffix++) {
            $anchor = "{$base}-{$suffix}";
        }

        $used[$anchor] = true;

        return $anchor;
    }
}
