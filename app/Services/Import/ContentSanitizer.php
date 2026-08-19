<?php

namespace App\Services\Import;

use App\Exceptions\ImportValidationException;
use App\Rules\ValidMarkdown;
use App\Services\HtmlSanitizer;
use App\Support\AuthorMarkdown;
use Throwable;

/**
 * Rejects imported HTML or Markdown that exceeds the app's shared allow-list.
 *
 * Normal form saves strip disallowed HTML. Imports fail instead of changing bulk
 * content silently. Rendered Markdown receives the same check because it can pass
 * raw HTML through.
 */
class ContentSanitizer
{
    public function __construct(private HtmlSanitizer $htmlSanitizer) {}

    /** @throws ImportValidationException When cleaning changes the fragment. */
    public function assertHtmlAllowed(string $html): void
    {
        $cleaned = $this->htmlSanitizer->clean($html);

        if ($this->canonicalize($cleaned) !== $this->canonicalize($html)) {
            throw ImportValidationException::disallowedHtmlContent();
        }
    }

    /** @throws ImportValidationException For invalid Markdown or rendered HTML. */
    public function assertMarkdownAllowed(string $markdown): void
    {
        // Convert the validation callback to the import exception type.
        $markdownIsInvalid = false;

        (new ValidMarkdown)->validate('contents', $markdown, function () use (&$markdownIsInvalid): void {
            $markdownIsInvalid = true;
        });

        if ($markdownIsInvalid) {
            throw ImportValidationException::invalidMarkdown();
        }

        // Validate the exact rendered form that the app later displays.
        try {
            $rendered = AuthorMarkdown::render($markdown);
        } catch (Throwable) {
            throw ImportValidationException::invalidMarkdown();
        }

        $this->assertHtmlAllowed($rendered);
    }

    /**
     * Normalizes harmless purifier serialization changes before comparison.
     *
     * This includes line endings, entities, void-element syntax, empty checkbox
     * values, and Boolean attribute syntax. Real removed markup still differs.
     */
    private function canonicalize(string $html): string
    {
        $normalizedNewlines = str_replace(["\r\n", "\r"], "\n", $html);
        $decoded = html_entity_decode($normalizedNewlines, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $normalized = preg_replace('/\s*\/>/', '>', $decoded);
        $normalized = preg_replace('/\svalue=""/', '', (string) $normalized);
        $normalized = preg_replace('/\b(checked|disabled)="(?:checked|disabled|)"/', '$1', (string) $normalized);

        return trim((string) $normalized);
    }
}
