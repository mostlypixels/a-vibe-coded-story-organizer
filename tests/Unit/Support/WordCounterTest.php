<?php

namespace Tests\Unit\Support;

use App\Enums\FieldKind;
use App\Support\WordCounter;
use Tests\TestCase;

/**
 * The fixture table in
 * `.specs/planned/2026-07/word-count/expanded/testing.md` IS the specification
 * of "what is a word" — every row there has a test here.
 */
class WordCounterTest extends TestCase
{
    public function test_null_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count(null, FieldKind::Plain));
    }

    public function test_empty_string_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count('', FieldKind::Plain));
    }

    public function test_whitespace_only_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count('   ', FieldKind::Plain));
    }

    public function test_runs_of_spaces_collapse(): void
    {
        $this->assertSame(3, WordCounter::count('one two   three', FieldKind::Plain));
    }

    public function test_blank_lines_are_separators(): void
    {
        $this->assertSame(2, WordCounter::count("one\n\ntwo", FieldKind::Plain));
    }

    public function test_unicode_letters_and_apostrophes_do_not_split(): void
    {
        $this->assertSame(2, WordCounter::count("état d'âme", FieldKind::Plain));
    }

    public function test_hyphenated_word_stays_one_token(): void
    {
        $this->assertSame(1, WordCounter::count("jack-o'-lantern", FieldKind::Plain));
    }

    public function test_em_dash_without_spaces_does_not_split(): void
    {
        // Q1: whitespace only splits words — an em-dash glues, it does not separate.
        $this->assertSame(1, WordCounter::count('one—two', FieldKind::Plain));
    }

    public function test_a_number_with_a_thousands_separator_is_one_word(): void
    {
        $this->assertSame(1, WordCounter::count('1,234', FieldKind::Plain));
    }

    public function test_a_decimal_number_is_one_word(): void
    {
        $this->assertSame(1, WordCounter::count('3.5', FieldKind::Plain));
    }

    public function test_a_scene_divider_has_no_words(): void
    {
        // Q2b: no letter and no digit in any token, so the whole divider drops out.
        $this->assertSame(0, WordCounter::count('* * *', FieldKind::Markdown));
    }

    public function test_a_lone_em_dash_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count('—', FieldKind::Plain));
    }

    public function test_punctuation_only_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count('"  ...  |', FieldKind::Plain));
    }

    public function test_markdown_emphasis_markers_are_not_words(): void
    {
        $this->assertSame(2, WordCounter::count('**bold** text', FieldKind::Markdown));
    }

    public function test_markdown_heading_marker_is_not_a_word(): void
    {
        $this->assertSame(1, WordCounter::count('# Heading', FieldKind::Markdown));
    }

    public function test_markdown_link_counts_the_label_not_the_url(): void
    {
        $this->assertSame(1, WordCounter::count('[link](http://x.com)', FieldKind::Markdown));
    }

    public function test_markdown_inline_code_counts_as_words(): void
    {
        // Q2: the deliberate asymmetry with fenced blocks — inline code sits inside
        // a sentence, so it counts.
        $this->assertSame(5, WordCounter::count('She typed `rm -rf` now', FieldKind::Markdown));
    }

    public function test_markdown_fenced_block_alone_is_zero_words(): void
    {
        $this->assertSame(0, WordCounter::count("```\nfenced words here\n```", FieldKind::Markdown));
    }

    public function test_markdown_only_the_fence_is_removed(): void
    {
        $this->assertSame(2, WordCounter::count("before\n```\ncode\n```\nafter", FieldKind::Markdown));
    }

    public function test_markdown_tilde_fence_is_removed(): void
    {
        $this->assertSame(2, WordCounter::count("before\n~~~\ncode words\n~~~\nafter", FieldKind::Markdown));
    }

    public function test_markdown_fence_info_string_is_not_counted(): void
    {
        $this->assertSame(2, WordCounter::count("before\n```php\n\$x = 1;\n```\nafter", FieldKind::Markdown));
    }

    public function test_markdown_empty_fence_is_removed(): void
    {
        $this->assertSame(2, WordCounter::count("before\n```\n```\nafter", FieldKind::Markdown));
    }

    public function test_markdown_indented_fence_is_removed(): void
    {
        // Up to three spaces is still a fence to CommonMark, so it must not count.
        $this->assertSame(2, WordCounter::count("before\n   ```\n   code words\n   ```\nafter", FieldKind::Markdown));
    }

    public function test_markdown_longer_closing_fence_closes_the_block(): void
    {
        // ```` closes ``` — so "after" is prose, not part of the code block.
        $this->assertSame(2, WordCounter::count("before\n```\ncode words\n````\nafter", FieldKind::Markdown));
    }

    public function test_markdown_a_tilde_fence_does_not_close_a_backtick_fence(): void
    {
        // CommonMark keeps the block open, so everything after it is code.
        $this->assertSame(1, WordCounter::count("before\n```\ncode words\n~~~\nmore code", FieldKind::Markdown));
    }

    public function test_markdown_unclosed_fence_runs_to_the_end(): void
    {
        // CommonMark treats the rest of the document as code, so only "before" counts.
        $this->assertSame(1, WordCounter::count("before\n```php\nsecret code here", FieldKind::Markdown));
    }

    public function test_rich_heading_must_not_glue_into_the_next_paragraph(): void
    {
        // Q9: proves task 1's RichText::toPlainText() fix landed — without it this
        // undercounts by one.
        $this->assertSame(
            4,
            WordCounter::count('<h1>Chapter One</h1><p>She waited.</p>', FieldKind::Rich)
        );
    }

    public function test_rich_list_items_must_not_glue_together(): void
    {
        $this->assertSame(
            2,
            WordCounter::count('<ul><li>alpha</li><li>beta</li></ul>', FieldKind::Rich)
        );
    }

    public function test_rich_block_boundary_is_a_separator(): void
    {
        $this->assertSame(2, WordCounter::count('<p>one</p><p>two</p>', FieldKind::Rich));
    }
}
