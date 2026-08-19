<?php

namespace Tests\Unit\Support;

use App\Services\HtmlSanitizer;
use App\Support\AuthorMarkdown;
use App\Support\RichTextFields;
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

    public function test_null_renders_as_an_empty_string(): void
    {
        $this->assertSame('', AuthorMarkdown::render(null));
    }
}
