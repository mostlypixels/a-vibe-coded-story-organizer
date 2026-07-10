<?php

namespace Tests\Unit;

use App\Support\RichText;
use Tests\TestCase;

/**
 * Unit tests for the rich-HTML → plain-text helper, extracted from
 * StaticSiteExporter so the rich-text feature owns it (see the
 * extract_shared_helpers spec).
 */
class RichTextTest extends TestCase
{
    public function test_null_and_empty_yield_an_empty_string(): void
    {
        $this->assertSame('', RichText::toPlainText(null));
        $this->assertSame('', RichText::toPlainText(''));
    }

    public function test_it_strips_tags_and_keeps_the_prose(): void
    {
        $this->assertSame(
            'An epic about courage.',
            RichText::toPlainText('<p>An epic about <strong>courage</strong>.</p>')
        );
    }

    public function test_paragraph_boundaries_become_line_breaks(): void
    {
        $this->assertSame(
            "First paragraph.\nSecond paragraph.",
            RichText::toPlainText('<p>First paragraph.</p><p>Second paragraph.</p>')
        );
    }

    public function test_br_tags_become_line_breaks(): void
    {
        $this->assertSame(
            "Line one\nLine two",
            RichText::toPlainText('Line one<br>Line two')
        );
    }

    public function test_it_decodes_html_entities(): void
    {
        $this->assertSame('Salt & pepper', RichText::toPlainText('<p>Salt &amp; pepper</p>'));
    }

    public function test_runs_of_blank_lines_are_collapsed(): void
    {
        $this->assertSame(
            "Top.\n\nBottom.",
            RichText::toPlainText('<p>Top.</p><br><br><br><p>Bottom.</p>')
        );
    }
}
