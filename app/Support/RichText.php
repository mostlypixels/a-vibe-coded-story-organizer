<?php

namespace App\Support;

use App\Services\HtmlSanitizer;

/**
 * Helpers for the stored rich-HTML text feature (the fields listed in
 * {@see RichTextFields}, sanitized on write by {@see HtmlSanitizer}).
 * This is the home for turning that stored HTML into other representations — the
 * knowledge of the editor's HTML shape belongs here, beside the sanitizer, not in
 * unrelated modules such as the zip exporter.
 */
class RichText
{
    /**
     * Reduce a stored rich-HTML fragment to plain text. Block boundaries (`</p>`,
     * `<br>`) become line breaks so paragraphs survive, remaining tags are stripped,
     * entities are decoded, and runs of blank lines are collapsed. A null/empty value
     * yields an empty string (callers then omit the block entirely).
     */
    public static function toPlainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // Turn paragraph/line-break boundaries into newlines before stripping tags, so
        // multi-paragraph text doesn't collapse into one run-on line.
        $withBreaks = preg_replace('/<\/p>|<br\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5);

        // Collapse 3+ newlines to a single blank line and trim trailing spaces per line.
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
