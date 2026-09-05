<?php

namespace App\Services\Import;

use App\Enums\RichTextProfile;
use App\Exceptions\ImportValidationException;
use App\Rules\ValidMarkdown;
use App\Services\HtmlSanitizer;
use App\Support\AuthorMarkdown;
use App\Support\CanonicalPunctuation;
use Throwable;

/**
 * Rejects imported HTML or Markdown that exceeds the app's shared allow-list.
 *
 * Normal form saves strip disallowed HTML. Imports fail instead of changing bulk
 * content silently. Rendered Markdown receives the same check because it can pass
 * raw HTML through.
 *
 * One exception to "no silent change": the returned content has its punctuation
 * normalized to the app's convention (`CanonicalPunctuation`). The allow-list
 * still fails an import; normalization runs only after the allow-list passes,
 * and it changes punctuation only. Callers must store the returned string.
 */
class ContentSanitizer
{
    public function __construct(private HtmlSanitizer $htmlSanitizer) {}

    /**
     * @return string The fragment with canonical punctuation.
     *
     * @throws ImportValidationException When cleaning changes the fragment.
     */
    public function assertHtmlAllowed(string $html, RichTextProfile $profile = RichTextProfile::Rich): string
    {
        $cleaned = $this->htmlSanitizer->clean($html, $profile);

        if ($this->canonicalize($cleaned) !== $this->canonicalize($html)) {
            throw ImportValidationException::disallowedHtmlContent();
        }

        return CanonicalPunctuation::inHtml($html);
    }

    /**
     * @return string The Markdown with canonical punctuation.
     *
     * @throws ImportValidationException For invalid Markdown or rendered HTML.
     */
    public function assertMarkdownAllowed(string $markdown): string
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
            $rendered = AuthorMarkdown::renderUnsanitized($markdown);
        } catch (Throwable) {
            throw ImportValidationException::invalidMarkdown();
        }

        // Rendered Markdown must clear the same Structural bar that
        // AuthorMarkdown::render() applies, so an import carrying a decorative
        // class is rejected instead of silently stripped later.
        $this->assertHtmlAllowed($rendered, RichTextProfile::Structural);

        return CanonicalPunctuation::inMarkdown($markdown);
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
