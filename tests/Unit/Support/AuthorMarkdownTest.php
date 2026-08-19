<?php

namespace Tests\Unit\Support;

use App\Services\HtmlSanitizer;
use App\Support\AuthorMarkdown;
use App\Support\RichTextFields;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Strikethrough must render as `<s>`, not CommonMark's default `<del>`.
 *
 * `<del>` belongs to generated revision diffs and is not an author tag (see
 * RichTextFieldsDiffTagsTest), so the sanitizer strips it. Rendering `~~...~~`
 * as `<del>` therefore made every strikethrough paragraph fail the import
 * allow-list check and lose its formatting on screen.
 */
class AuthorMarkdownTest extends TestCase
{
    public function test_strikethrough_renders_as_s(): void
    {
        $html = AuthorMarkdown::render('~~Dear~~ friend');

        $this->assertStringContainsString('<s>Dear</s>', $html);
        $this->assertStringNotContainsString('<del>', $html);
    }

    public function test_strikethrough_survives_the_sanitizer(): void
    {
        $rendered = AuthorMarkdown::render('~~Dear~~ friend');

        $this->assertStringContainsString('<s>Dear</s>', app(HtmlSanitizer::class)->clean($rendered));
    }

    public function test_the_rendered_tag_is_an_allowed_author_tag(): void
    {
        $this->assertContains('s', RichTextFields::ALLOWED_TAGS);
        $this->assertNotContains('del', RichTextFields::ALLOWED_TAGS);
    }

    public function test_other_github_flavoured_markdown_still_renders(): void
    {
        $html = AuthorMarkdown::render("# Title\n\n- [x] done\n\n**bold**");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('checkbox', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    // ------------------------------------------------------------------
    // Raw HTML in Markdown source
    // ------------------------------------------------------------------

    /**
     * Markdown allows raw HTML inline and ValidMarkdown rejects none of it, so
     * the rendered output is untrusted markup echoed with {!! !!} — including on
     * the unauthenticated share route.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unsafeMarkdown(): array
    {
        return [
            'event handler' => ['<img src=x onerror="alert(1)">', 'onerror'],
            'javascript link' => ['<a href="javascript:alert(1)">c</a>', 'javascript:'],
            'inline svg' => ['<svg onload=alert(1)></svg>', 'onload'],
            'iframe' => ['<iframe src="https://evil.test"></iframe>', '<iframe'],
            'inline style' => ['<div style="position:fixed">x</div>', 'style='],
            'form control' => ['<button onclick="alert(1)">go</button>', 'onclick'],
        ];
    }

    #[DataProvider('unsafeMarkdown')]
    public function test_render_strips_unsafe_raw_html(string $markdown, string $needle): void
    {
        $this->assertStringNotContainsString($needle, AuthorMarkdown::render($markdown));
    }

    /**
     * The editor writes these three as raw HTML on purpose: CommonMark has no
     * syntax for them (see resources/js/wysiwyg.js). Sanitizing must keep them,
     * which is why rendering sanitizes rather than escaping raw HTML outright.
     */
    public function test_render_keeps_the_raw_html_the_editor_itself_writes(): void
    {
        $html = AuthorMarkdown::render('<u>under</u> <sub>low</sub> <sup>high</sup>');

        $this->assertStringContainsString('<u>under</u>', $html);
        $this->assertStringContainsString('<sub>low</sub>', $html);
        $this->assertStringContainsString('<sup>high</sup>', $html);
    }

    public function test_render_keeps_ordinary_prose_and_structure_intact(): void
    {
        $html = AuthorMarkdown::render('# Title

Prose with [a link](https://example.test).

| a | b |
| --- | --- |
| 1 | 2 |');

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('href="https://example.test"', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    /** The import allow-list check needs to see the raw HTML in order to reject it. */
    public function test_render_unsanitized_leaves_raw_html_alone(): void
    {
        $this->assertStringContainsString('onerror', AuthorMarkdown::renderUnsanitized('<img src=x onerror="alert(1)">'));
    }

    public function test_null_renders_as_an_empty_string(): void
    {
        $this->assertSame('', AuthorMarkdown::render(null));
    }
}
