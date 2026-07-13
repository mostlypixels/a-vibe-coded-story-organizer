<?php

namespace App\Services\Import;

use App\Exceptions\ImportValidationException;
use App\Rules\ValidMarkdown;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;
use Throwable;

/**
 * The import-side content gate for the two kinds of prose an export archive
 * carries: raw rich-HTML fragments (description.html / notes.html) and Markdown
 * (a scene's contents.md).
 *
 * It composes the app's EXISTING allow-list — {@see HtmlSanitizer}, configured
 * from RichTextFields::ALLOWED_TAGS/ALLOWED_SCHEMES — with an import-specific
 * policy that is deliberately stricter than a normal form save:
 *
 *   * A normal save (SanitizesRichHtml) silently STRIPS anything outside the
 *     allow-list and persists the cleaned result.
 *   * Import instead REJECTS the whole archive on any violation. A bulk
 *     untrusted upload deserves a hard failure with a clear error, never a
 *     quietly-altered import (.specs → import → open-questions.md, question 4).
 *
 * The allow-list itself is never duplicated here — only the reject-vs-strip
 * policy is new. The check works by cleaning the content through the shared
 * sanitizer and comparing the result to the input: if cleaning changed
 * anything, something sat outside the allow-list.
 *
 * For Markdown, CommonMark passes raw HTML through to its rendered output
 * untouched (the "raw-HTML passthrough hole" in architecture.md), so
 * well-formedness alone is not enough: the RENDERED HTML is run through the
 * same allow-list check as a native HTML fragment.
 */
class ContentSanitizer
{
    public function __construct(private HtmlSanitizer $htmlSanitizer) {}

    /**
     * Assert an HTML fragment contains nothing outside the app's rich-text
     * allow-list — no disallowed tags, no attributes beyond a[href], no
     * disallowed URL schemes (javascript:, data:, ...).
     *
     * @throws ImportValidationException on any violation
     */
    public function assertHtmlAllowed(string $html): void
    {
        $cleaned = $this->htmlSanitizer->clean($html);

        if ($this->canonicalize($cleaned) !== $this->canonicalize($html)) {
            throw ImportValidationException::disallowedHtmlContent();
        }
    }

    /**
     * Assert a Markdown document is well-formed AND that its rendered HTML
     * stays inside the allow-list.
     *
     * Well-formedness reuses the app's existing {@see ValidMarkdown} rule (the
     * same gate scene contents pass on a normal save). Rendering uses
     * Str::markdown(), the exact renderer Scene::renderedContents() and the
     * book export use — so what is checked here is what the app would later
     * echo with {!! !!}.
     *
     * @throws ImportValidationException on unparseable Markdown or a rendered-HTML violation
     */
    public function assertMarkdownAllowed(string $markdown): void
    {
        // ValidMarkdown reports failure through its $fail callback (it never
        // throws itself); capture that as a flag and surface it as the same
        // exception type every other import check throws.
        $markdownIsInvalid = false;

        (new ValidMarkdown)->validate('contents', $markdown, function () use (&$markdownIsInvalid): void {
            $markdownIsInvalid = true;
        });

        if ($markdownIsInvalid) {
            throw ImportValidationException::invalidMarkdown();
        }

        // Render exactly as the app does elsewhere. Str::markdown() uses the
        // GFM converter (ValidMarkdown uses plain CommonMark), so keep a belt
        // over the braces: if THIS renderer chokes where the rule's did not,
        // that is still "not Markdown this app can import".
        try {
            $rendered = Str::markdown($markdown);
        } catch (Throwable) {
            throw ImportValidationException::invalidMarkdown();
        }

        // The passthrough hole: raw HTML inside Markdown survives rendering
        // verbatim, so the rendered output gets the same allow-list check as
        // a native description.html/notes.html fragment.
        $this->assertHtmlAllowed($rendered);
    }

    /**
     * Put a fragment into the canonical form used for the changed-by-cleaning
     * comparison. HTMLPurifier normalizes things that carry no security
     * meaning — \r\n line endings become \n, and inert text entities are
     * re-encoded (CommonMark writes `&quot;`, purifier re-emits a literal `"`)
     * — so both sides are normalized the same way before comparing, otherwise
     * ordinary prose containing a double quote would be a false positive.
     *
     * Decoding entities cannot mask an attack: encoded markup (`&lt;script&gt;`)
     * is inert text on BOTH sides of the comparison, while real disallowed
     * markup is removed from the cleaned side and still mismatches.
     */
    private function canonicalize(string $html): string
    {
        $normalizedNewlines = str_replace(["\r\n", "\r"], "\n", $html);

        return trim(html_entity_decode($normalizedNewlines, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
