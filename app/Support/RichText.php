<?php

namespace App\Support;

use App\Services\EpubExporter;
use App\Services\HtmlSanitizer;
use DOMDocument;

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

    /**
     * Normalise a stored rich-HTML fragment into well-formed XHTML suitable for
     * embedding directly in an EPUB content document.
     *
     * The sanitizer's output is safe HTML, but HTML is not XML: it may carry void
     * elements written as `<br>`/`<hr>` (not self-closed) or leave a block element
     * unclosed. Embedding that raw would make the shipped `.xhtml` fail EpubExporter's
     * well-formedness gate ({@see EpubExporter::validatePackage()}). We
     * therefore round-trip it through {@see DOMDocument}: the HTML parser repairs the
     * markup (closing tags, self-closing void elements), and `saveXML` re-serialises it
     * as XML. A null/blank value yields an empty string so callers can omit the block
     * entirely rather than emitting an empty element.
     *
     * Only the parsed body's children are serialised, so none of the `<html>`/`<body>`
     * scaffolding the HTML parser implies leaks into the fragment.
     */
    public static function toXhtmlFragment(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        // The leading XML processing instruction is the standard hint that forces
        // loadHTML to read the bytes as UTF-8 (it assumes ISO-8859-1 otherwise, which
        // would mangle multibyte characters). LIBXML_HTML_NODEFDTD keeps it from adding
        // a doctype; the implied <html>/<body> wrapper is stripped back off below.
        $document->loadHTML(
            '<?xml encoding="UTF-8"?>'.$html,
            LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        $fragment = '';
        foreach ($body->childNodes as $child) {
            $fragment .= $document->saveXML($child);
        }

        return trim($fragment);
    }
}
