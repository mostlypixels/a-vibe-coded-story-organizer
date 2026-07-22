<?php

namespace Tests\Unit\Services;

use App\Enums\FieldKind;
use App\Services\RevisionDiffer;
use Tests\TestCase;

/**
 * Task 10 — App\Services\RevisionDiffer, the diff service behind the compare
 * view (handoff.md §5.3). Rich fields diff RichText::toPlainText() output;
 * Markdown/plain fields diff the raw stored text directly.
 */
class RevisionDifferTest extends TestCase
{
    private RevisionDiffer $differ;

    protected function setUp(): void
    {
        parent::setUp();

        $this->differ = new RevisionDiffer;
    }

    public function test_a_rich_field_with_a_real_prose_change_produces_a_diff(): void
    {
        $result = $this->differ->diff(
            FieldKind::Rich,
            '<p>The cat sat.</p>',
            '<p>The cat sat quietly.</p>',
        );

        $this->assertFalse($result->formattingChangedOnly);
        $this->assertStringContainsString('quietly', $result->html);
    }

    public function test_a_rich_field_whose_only_difference_is_markup_reports_formatting_changed_only(): void
    {
        // Same reader-visible text, different wrapper tag — RichText::toPlainText()
        // reduces both to "Hello world".
        $result = $this->differ->diff(
            FieldKind::Rich,
            '<p>Hello world</p>',
            '<div>Hello world</div>',
        );

        $this->assertTrue($result->formattingChangedOnly);
        $this->assertNull($result->html);
    }

    public function test_a_markdown_field_diffs_the_raw_text_directly_not_a_plain_text_projection(): void
    {
        // The markup itself (the asterisks) IS the content here, so it must show
        // up in the diff rather than being stripped like a rich field's HTML.
        $result = $this->differ->diff(
            FieldKind::Markdown,
            'Hello world',
            'Hello **world**',
        );

        $this->assertFalse($result->formattingChangedOnly);
        $this->assertStringContainsString('**', $result->html);
    }

    public function test_a_plain_field_diffs_the_raw_text_directly(): void
    {
        $result = $this->differ->diff(FieldKind::Plain, 'All rights reserved.', 'All rights reserved, 2026.');

        $this->assertFalse($result->formattingChangedOnly);
        $this->assertStringContainsString('2026', $result->html);
    }

    public function test_null_values_are_treated_as_empty_strings(): void
    {
        $result = $this->differ->diff(FieldKind::Plain, null, 'New text');

        $this->assertFalse($result->formattingChangedOnly);
        $this->assertStringContainsString('New text', $result->html);
    }
}
