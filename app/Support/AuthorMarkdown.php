<?php

namespace App\Support;

use App\Enums\RichTextProfile;
use App\Services\HtmlSanitizer;
use App\Support\Markdown\StrikethroughSExtension;
use Illuminate\Support\Str;

/**
 * The one renderer for author-written Markdown (`Scene.contents` and Markdown
 * revisions). Wraps `Str::markdown()` so every caller gets the same GitHub
 * flavour, the same strikethrough tag, and — through {@see render()} — the same
 * safety guarantee.
 *
 * ## Why rendering must sanitize
 *
 * Markdown permits raw HTML inline, and CommonMark's default `html_input` is
 * `allow`. `ValidMarkdown` only proves the source *parses*; it rejects nothing.
 * So the rendered output of an author's Markdown is untrusted markup, exactly
 * like the WYSIWYG editor's HTML output is, and it is echoed with `{!! !!}` on
 * pages including the unauthenticated share route. It therefore passes through
 * {@see HtmlSanitizer} for the same reason the rich-HTML fields do.
 *
 * Escaping the raw HTML instead was rejected: the editor deliberately writes
 * `<u>`, `<sub>` and `<sup>` into Markdown because CommonMark has no syntax for
 * them (see resources/js/wysiwyg.js), and escaping would render an author's own
 * underline as literal angle brackets. Sanitizing keeps those three and drops
 * the rest.
 *
 * Rich-HTML fields are sanitized on *write* instead; `Scene.contents` cannot be,
 * because the stored value must stay Markdown source for the editor to reload.
 */
class AuthorMarkdown
{
    /**
     * Render for display. Safe to echo with `{!! !!}`.
     *
     * The Structural profile is the Markdown lock: scene text becomes EPUB body
     * and is read aloud, so it gets no decorative class even if the author types
     * the raw HTML.
     */
    public static function render(?string $markdown): string
    {
        return app(HtmlSanitizer::class)->clean(
            self::renderUnsanitized($markdown),
            RichTextProfile::Structural,
        );
    }

    /**
     * Render without sanitizing. **Never echo this.**
     *
     * Two callers need the unsanitized form: `ContentSanitizer` compares it
     * against its own cleaned form to detect disallowed markup in an import, so
     * cleaning first would make that check pass everything; and `WordCounter`
     * strips every tag immediately afterwards, so sanitizing would only cost
     * time and shift counts.
     */
    public static function renderUnsanitized(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [], [new StrikethroughSExtension]);
    }
}
