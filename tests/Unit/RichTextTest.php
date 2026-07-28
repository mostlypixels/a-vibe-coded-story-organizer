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

    /**
     * The regression this class exists to pin: `strip_tags()` alone glues the last
     * word of one block to the first of the next, so a heading ran into its
     * paragraph ("Chapter OneShe waited.") — wrong in search snippets, and one word
     * short per boundary for anything counting words.
     */
    public function test_every_block_boundary_becomes_a_line_break(): void
    {
        $this->assertSame(
            "Chapter One\nShe waited.",
            RichText::toPlainText('<h1>Chapter One</h1><p>She waited.</p>')
        );

        $this->assertSame(
            "alpha\nbeta",
            RichText::toPlainText('<ul><li>alpha</li><li>beta</li></ul>')
        );

        $this->assertSame(
            "quoted words\nafter",
            RichText::toPlainText('<blockquote>quoted words</blockquote><p>after</p>')
        );

        $this->assertSame(
            "left\nright",
            RichText::toPlainText('<table><tbody><tr><td>left</td><td>right</td></tr></tbody></table>')
        );
    }

    public function test_nested_blocks_break_once_per_boundary_not_once_per_tag(): void
    {
        // </p> and </blockquote> land back to back; the blank-line collapse keeps
        // that to a single blank line rather than a stack of newlines.
        $this->assertSame(
            "a\n\nb",
            RichText::toPlainText('<blockquote><p>a</p></blockquote><p>b</p>')
        );
    }

    public function test_inline_elements_do_not_break_the_line(): void
    {
        // The counterpart to the block rule: breaking on <strong>/<code>/<a> would
        // split a sentence — and a word count — at every emphasis.
        $this->assertSame(
            'She read the tome of naming aloud.',
            RichText::toPlainText('<p>She <strong>read</strong> the <em>tome</em> of <code>naming</code> <a href="https://example.test">aloud</a>.</p>')
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
