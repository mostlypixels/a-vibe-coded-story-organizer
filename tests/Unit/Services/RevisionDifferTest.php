<?php

namespace Tests\Unit\Services;

use App\Enums\FieldKind;
use App\Services\RevisionDiffer;
use Tests\TestCase;

/**
 * App\Services\RevisionDiffer — the router that picks a diff strategy from the
 * field's kind (expanded/diffing.md, *Two diff strategies, chosen by
 * `FieldKind`*).
 *
 * These tests are about *which* differ ran, not about how well it diffs: the
 * visual differ and its renderer have their own suites
 * (VisualHtmlDifferTest, DiffHtmlRendererTest), as does jfcherng upstream. So
 * each case here asserts on the output *shape* — rendered blocks versus a
 * side-by-side table — which is the thing routing decides.
 */
class RevisionDifferTest extends TestCase
{
    private RevisionDiffer $differ;

    protected function setUp(): void
    {
        parent::setUp();

        $this->differ = $this->app->make(RevisionDiffer::class);
    }

    public function test_a_rich_field_is_rendered_as_blocks_by_the_visual_differ(): void
    {
        $result = $this->differ->diff(
            FieldKind::Rich,
            '<p>The cat sat.</p>',
            '<p>The cat sat quietly.</p>',
        );

        // Block markup with the change marked in place — the visual differ ran.
        $this->assertStringContainsString('<p', $result->html);
        $this->assertStringContainsString('<ins', $result->html);
        $this->assertStringContainsString('quietly', $result->html);
        $this->assertStringNotContainsString('<table', $result->html);
        $this->assertSame(1, $result->changeCount);
    }

    public function test_a_rich_field_whose_only_difference_is_formatting_still_produces_a_diff(): void
    {
        // Same words, one of them newly bolded. The old plain-text projection
        // reduced both sides to "Hello world" and could only say "formatting
        // changed only"; the visual differ reports the change itself.
        $result = $this->differ->diff(
            FieldKind::Rich,
            '<p>Hello world</p>',
            '<p>Hello <strong>world</strong></p>',
        );

        $this->assertNotSame('', $result->html);
        $this->assertTrue($result->hasChanges());
        $this->assertStringContainsString('world', $result->html);
    }

    public function test_identical_rich_values_report_no_changes(): void
    {
        $result = $this->differ->diff(FieldKind::Rich, '<p>Unchanged.</p>', '<p>Unchanged.</p>');

        $this->assertSame(0, $result->changeCount);
        $this->assertFalse($result->hasChanges());
    }

    public function test_a_markdown_field_diffs_the_raw_text_with_the_source_differ(): void
    {
        // The markup itself (the asterisks) IS the content here: Scene.contents
        // is Markdown the writer typed, so it must survive into the diff rather
        // than being tokenized away as if it were rich HTML.
        $result = $this->differ->diff(
            FieldKind::Markdown,
            'Hello world',
            'Hello **world**',
        );

        $this->assertStringContainsString('<table', $result->html);
        $this->assertStringContainsString('**', $result->html);
        $this->assertSame(1, $result->changeCount);
    }

    public function test_a_markdown_field_keeps_its_block_markup_out_of_the_tokenizer(): void
    {
        // A heading and a blockquote are exactly what HtmlTokenizer would turn
        // into blocks if this field were ever routed to it. Seeing the literal
        // `#` and `>` in the output proves it was not.
        $result = $this->differ->diff(
            FieldKind::Markdown,
            "# Chapter one\n\n> She waited.",
            "# Chapter two\n\n> She waited.",
        );

        $this->assertStringContainsString('#', $result->html);
        $this->assertStringContainsString('&gt;', $result->html);
    }

    public function test_a_plain_field_diffs_the_raw_text_with_the_source_differ(): void
    {
        $result = $this->differ->diff(FieldKind::Plain, 'All rights reserved.', 'All rights reserved, 2026.');

        $this->assertStringContainsString('<table', $result->html);
        $this->assertStringContainsString('2026', $result->html);
        $this->assertTrue($result->hasChanges());
    }

    public function test_identical_source_values_report_no_changes(): void
    {
        $result = $this->differ->diff(FieldKind::Plain, 'All rights reserved.', 'All rights reserved.');

        $this->assertSame(0, $result->changeCount);
    }

    public function test_null_values_are_treated_as_empty(): void
    {
        $plain = $this->differ->diff(FieldKind::Plain, null, 'New text');
        $rich = $this->differ->diff(FieldKind::Rich, null, '<p>New paragraph</p>');

        $this->assertStringContainsString('New text', $plain->html);
        $this->assertStringContainsString('New paragraph', $rich->html);
        $this->assertTrue($plain->hasChanges());
        $this->assertTrue($rich->hasChanges());
    }
}
