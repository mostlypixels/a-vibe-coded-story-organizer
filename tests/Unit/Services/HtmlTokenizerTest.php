<?php

namespace Tests\Unit\Services;

use App\Services\Diff\HtmlTokenizer;
use App\Support\HtmlBlock;
use App\Support\InlineToken;
use Tests\TestCase;

/**
 * App\Services\Diff\HtmlTokenizer, the first stage of the in-house visual
 * differ: purified rich HTML in, a flat list of HtmlBlocks out.
 *
 * Proven here in isolation, because the diff engine is the riskiest part of the
 * feature.
 */
class HtmlTokenizerTest extends TestCase
{
    private HtmlTokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenizer = new HtmlTokenizer;
    }

    /** @return list<string> */
    private function words(HtmlBlock $block): array
    {
        return array_map(fn (InlineToken $token): string => $token->word, $block->tokens);
    }

    // ---------------------------------------------------------------------
    // Block vocabulary
    // ---------------------------------------------------------------------

    public function test_a_paragraph_becomes_one_block_carrying_its_words(): void
    {
        $blocks = $this->tokenizer->tokenize('<p>The cat sat.</p>');

        $this->assertCount(1, $blocks);
        $this->assertSame('p', $blocks[0]->tag);
        $this->assertSame('The cat sat.', $blocks[0]->text);
        $this->assertSame(['The', 'cat', 'sat.'], $this->words($blocks[0]));
    }

    public function test_each_heading_level_becomes_its_own_block(): void
    {
        $blocks = $this->tokenizer->tokenize('<h1>One</h1><h2>Two</h2><h3>Three</h3><h4>Four</h4>');

        $this->assertSame(['h1', 'h2', 'h3', 'h4'], array_map(fn (HtmlBlock $b): string => $b->tag, $blocks));
        $this->assertSame(['One', 'Two', 'Three', 'Four'], array_map(fn (HtmlBlock $b): string => $b->text, $blocks));
    }

    public function test_a_list_item_records_its_list_type_and_nesting_depth(): void
    {
        $blocks = $this->tokenizer->tokenize('<ol><li>Outer<ul><li>Inner</li></ul></li></ol>');

        $this->assertCount(2, $blocks);

        $this->assertSame('li', $blocks[0]->tag);
        $this->assertSame('Outer', $blocks[0]->text);
        $this->assertSame('ol', $blocks[0]->attributes['list-type']);
        $this->assertSame(1, $blocks[0]->attributes['depth']);

        // The nested item is its own block at depth 2 — not text folded into its
        // parent, which would make an edit inside it look like an edit to the
        // outer item.
        $this->assertSame('Inner', $blocks[1]->text);
        $this->assertSame('ul', $blocks[1]->attributes['list-type']);
        $this->assertSame(2, $blocks[1]->attributes['depth']);
    }

    public function test_a_task_list_item_keeps_its_checked_state(): void
    {
        $blocks = $this->tokenizer->tokenize(
            '<ul data-type="taskList"><li data-type="taskItem" data-checked="true">'
            .'<label><input type="checkbox" checked></label><div><p>Buy milk</p></div></li></ul>'
        );

        $this->assertCount(1, $blocks);
        $this->assertSame('task', $blocks[0]->attributes['list-type']);
        $this->assertSame('true', $blocks[0]->attributes['data-checked']);
        // The checkbox input contributes no words — the state lives in
        // data-checked, and counting it twice would be noise in the diff.
        $this->assertSame('Buy milk', $blocks[0]->text);
    }

    public function test_a_blockquote_keeps_its_callout_type(): void
    {
        $blocks = $this->tokenizer->tokenize('<blockquote data-callout-type="warning"><p>Mind the gap</p></blockquote>');

        $this->assertCount(1, $blocks);
        $this->assertSame('blockquote', $blocks[0]->tag);
        $this->assertSame('warning', $blocks[0]->attributes['data-callout-type']);
        $this->assertSame('Mind the gap', $blocks[0]->text);
    }

    public function test_a_code_block_becomes_one_pre_block(): void
    {
        $blocks = $this->tokenizer->tokenize('<pre><code>php artisan serve</code></pre>');

        $this->assertCount(1, $blocks);
        $this->assertSame('pre', $blocks[0]->tag);
        $this->assertSame('php artisan serve', $blocks[0]->text);
        $this->assertSame(['code'], $blocks[0]->tokens[0]->marks);
    }

    public function test_a_table_yields_one_block_per_row_with_cells_separated(): void
    {
        $blocks = $this->tokenizer->tokenize(
            '<table><thead><tr><th>Name</th><th>Role</th></tr></thead>'
            .'<tbody><tr><td>Melusine</td><td>Witch</td></tr></tbody></table>'
        );

        $this->assertCount(2, $blocks);
        $this->assertSame('tr', $blocks[0]->tag);
        $this->assertSame('Name'.HtmlBlock::CELL_SEPARATOR.'Role', $blocks[0]->text);

        // The token stream keeps the cell boundary, so the renderer can rebuild
        // the row rather than guessing where the cells were.
        $this->assertSame(
            ['Melusine', HtmlBlock::CELL_BOUNDARY, 'Witch'],
            $this->words($blocks[1]),
        );
    }

    public function test_a_horizontal_rule_is_a_void_block(): void
    {
        $blocks = $this->tokenizer->tokenize('<p>Before</p><hr><p>After</p>');

        $this->assertSame(['p', 'hr', 'p'], array_map(fn (HtmlBlock $b): string => $b->tag, $blocks));
        $this->assertTrue($blocks[1]->isVoid());
    }

    public function test_an_image_is_matched_by_its_source_not_its_alt_text(): void
    {
        $first = $this->tokenizer->tokenize('<p><img src="https://example.test/one.png" alt="A cat"></p>');
        $second = $this->tokenizer->tokenize('<p><img src="https://example.test/two.png" alt="A cat"></p>');

        $this->assertSame('img', $first[0]->tag);
        $this->assertSame('https://example.test/one.png', $first[0]->attributes['src']);
        $this->assertSame('A cat', $first[0]->attributes['alt']);

        // Swapping the picture behind identical alt text is a real change.
        $this->assertNotSame($first[0]->matchKey(), $second[0]->matchKey());
    }

    // ---------------------------------------------------------------------
    // Normalisation
    // ---------------------------------------------------------------------

    public function test_whitespace_runs_collapse_so_reserialised_html_tokenises_identically(): void
    {
        $spaced = $this->tokenizer->tokenize("<p>a  \n\t b</p>");
        $tight = $this->tokenizer->tokenize('<p>a b</p>');

        $this->assertSame($tight[0]->text, $spaced[0]->text);
        $this->assertSame($tight[0]->matchKey(), $spaced[0]->matchKey());
        $this->assertSame($tight[0]->signature, $spaced[0]->signature);
    }

    public function test_a_non_breaking_space_reads_as_ordinary_whitespace(): void
    {
        $blocks = $this->tokenizer->tokenize('<p>a&nbsp;b</p>');

        $this->assertSame('a b', $blocks[0]->text);
    }

    public function test_an_empty_paragraph_produces_no_block(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize('<p></p><p>   </p>'));
    }

    // ---------------------------------------------------------------------
    // Marks
    // ---------------------------------------------------------------------

    public function test_nested_marks_stack_outermost_first(): void
    {
        $blocks = $this->tokenizer->tokenize('<p><strong><em>loud</em></strong></p>');

        $this->assertSame(['strong', 'em'], $blocks[0]->tokens[0]->marks);
        $this->assertSame("strong,em\u{001F}loud", $blocks[0]->tokens[0]->comparable());
    }

    public function test_the_same_words_formatted_differently_share_text_but_not_signature(): void
    {
        $plain = $this->tokenizer->tokenize('<p>The cat sat</p>');
        $bolded = $this->tokenizer->tokenize('<p>The <strong>cat</strong> sat</p>');

        $this->assertSame($plain[0]->text, $bolded[0]->text);
        $this->assertNotSame($plain[0]->signature, $bolded[0]->signature);

        // matchKey() deliberately ignores marks, so the differ still pairs these
        // two blocks up and can report "formatting changed" instead of a delete
        // plus an unrelated insert.
        $this->assertSame($plain[0]->matchKey(), $bolded[0]->matchKey());
    }

    public function test_a_link_carries_its_target_so_repointing_it_is_a_change(): void
    {
        $first = $this->tokenizer->tokenize('<p><a href="https://one.test">here</a></p>');
        $second = $this->tokenizer->tokenize('<p><a href="https://two.test">here</a></p>');

        $this->assertSame(['a:https://one.test'], $first[0]->tokens[0]->marks);
        $this->assertNotSame($first[0]->tokens[0]->comparable(), $second[0]->tokens[0]->comparable());
        $this->assertNotSame($first[0]->signature, $second[0]->signature);
    }

    public function test_a_coloured_word_shares_text_and_match_key_but_not_signature(): void
    {
        $plain = $this->tokenizer->tokenize('<p>The cat sat</p>');
        $coloured = $this->tokenizer->tokenize('<p>The <span class="rt-color-red">cat</span> sat</p>');

        $this->assertSame(['color:red'], $coloured[0]->tokens[1]->marks);
        $this->assertSame($plain[0]->text, $coloured[0]->text);
        $this->assertNotSame($plain[0]->signature, $coloured[0]->signature);

        // The trap: colour must stay out of matchKey(), or a recolour reads as
        // a delete plus an insert instead of a formatting change.
        $this->assertSame($plain[0]->matchKey(), $coloured[0]->matchKey());
    }

    public function test_a_recoloured_word_changes_its_signature(): void
    {
        $red = $this->tokenizer->tokenize('<p><span class="rt-color-red">cat</span></p>');
        $blue = $this->tokenizer->tokenize('<p><span class="rt-color-blue">cat</span></p>');

        $this->assertNotSame($red[0]->signature, $blue[0]->signature);
        $this->assertSame($red[0]->matchKey(), $blue[0]->matchKey());
    }

    public function test_a_span_without_a_known_colour_contributes_no_mark(): void
    {
        $blocks = $this->tokenizer->tokenize('<p><span class="rt-color-chartreuse">cat</span> <span>sat</span></p>');

        $this->assertSame([[], []], array_map(fn (InlineToken $token): array => $token->marks, $blocks[0]->tokens));
        $this->assertSame('cat sat', $blocks[0]->text);
    }

    // ---------------------------------------------------------------------
    // Alignment
    // ---------------------------------------------------------------------

    public function test_a_realigned_paragraph_differs_in_attributes_and_signature_but_not_in_match_key(): void
    {
        $left = $this->tokenizer->tokenize('<p>The cat sat</p>');
        $centred = $this->tokenizer->tokenize('<p class="rt-align-center">The cat sat</p>');

        $this->assertSame([], $left[0]->attributes);
        $this->assertSame(['align' => 'center'], $centred[0]->attributes);

        // Alignment is "how it says it", so it belongs to the signature — an
        // alignment-only edit is a change a reader sees.
        $this->assertSame($left[0]->text, $centred[0]->text);
        $this->assertNotSame($left[0]->signature, $centred[0]->signature);

        // …and not to matchKey(), or the paragraph stops matching its old self.
        $this->assertSame($left[0]->matchKey(), $centred[0]->matchKey());
    }

    public function test_two_alignments_differ_from_each_other(): void
    {
        $centred = $this->tokenizer->tokenize('<p class="rt-align-center">The cat sat</p>');
        $justified = $this->tokenizer->tokenize('<p class="rt-align-justify">The cat sat</p>');

        $this->assertNotSame($centred[0]->signature, $justified[0]->signature);
        $this->assertSame($centred[0]->matchKey(), $justified[0]->matchKey());
    }

    public function test_a_heading_carries_its_alignment(): void
    {
        $blocks = $this->tokenizer->tokenize('<h2 class="rt-align-right">Title</h2>');

        $this->assertSame(['align' => 'right'], $blocks[0]->attributes);
    }

    public function test_an_unknown_alignment_class_is_ignored(): void
    {
        $blocks = $this->tokenizer->tokenize('<p class="rt-align-sideways prose">The cat sat</p>');

        $this->assertSame([], $blocks[0]->attributes);
    }

    // ---------------------------------------------------------------------
    // Robustness
    // ---------------------------------------------------------------------

    public function test_a_null_or_empty_value_yields_no_blocks(): void
    {
        $this->assertSame([], $this->tokenizer->tokenize(null));
        $this->assertSame([], $this->tokenizer->tokenize(''));
        $this->assertSame([], $this->tokenizer->tokenize('   '));
    }

    public function test_malformed_html_is_repaired_rather_than_throwing(): void
    {
        $blocks = $this->tokenizer->tokenize('<p>Unclosed <strong>bold');

        $this->assertCount(1, $blocks);
        $this->assertSame('Unclosed bold', $blocks[0]->text);
        $this->assertSame(['strong'], $blocks[0]->tokens[1]->marks);
    }

    public function test_bare_text_outside_any_block_still_yields_a_paragraph(): void
    {
        // A value that predates the editor, or a fragment the sanitizer left
        // unwrapped: its text must not silently vanish from the diff.
        $blocks = $this->tokenizer->tokenize('Just some words');

        $this->assertCount(1, $blocks);
        $this->assertSame('p', $blocks[0]->tag);
        $this->assertSame('Just some words', $blocks[0]->text);
    }

    public function test_a_tag_outside_the_vocabulary_is_ignored_rather_than_emitted(): void
    {
        $blocks = $this->tokenizer->tokenize('<section><p>Kept</p></section>');

        $this->assertCount(1, $blocks);
        $this->assertSame('p', $blocks[0]->tag);
        $this->assertSame('Kept', $blocks[0]->text);
    }

    // ------------------------------------------------------------------
    // Marks inside a word
    // ------------------------------------------------------------------

    /**
     * A mark that starts mid-word must not split the word. Before this, the
     * halves landed in two text nodes and became two words, so the block's text
     * and matchKey both differed from the unmarked version and the differ
     * reported a delete plus an insert instead of a formatting change.
     */
    public function test_a_mark_inside_a_word_keeps_the_word_whole(): void
    {
        $marked = $this->tokenizer->tokenize('<p>E = mc<sup>2</sup></p>')[0];
        $plain = $this->tokenizer->tokenize('<p>E = mc2</p>')[0];

        $this->assertSame(['E', '=', 'mc2'], $this->words($marked));
        $this->assertSame($plain->text, $marked->text);
        $this->assertSame($plain->matchKey(), $marked->matchKey());
        $this->assertNotSame($plain->signature, $marked->signature);
    }

    public function test_a_word_spanning_a_mark_carries_that_mark(): void
    {
        $block = $this->tokenizer->tokenize('<p>un<strong>believ</strong>able</p>')[0];

        $this->assertSame(['unbelievable'], $this->words($block));
        $this->assertSame(['strong'], $block->tokens[0]->marks);
    }

    public function test_subscript_and_superscript_are_marks(): void
    {
        $this->assertContains('sub', HtmlTokenizer::MARK_TAGS);
        $this->assertContains('sup', HtmlTokenizer::MARK_TAGS);
    }

    /** A `br` is a real word boundary, so it must survive the segment pass. */
    public function test_a_line_break_still_separates_words(): void
    {
        $block = $this->tokenizer->tokenize('<p>one<br>two</p>')[0];

        $this->assertSame(['one', 'two'], $this->words($block));
    }

    public function test_a_table_row_does_not_glue_adjoining_cells_into_one_word(): void
    {
        $block = $this->tokenizer->tokenize('<table><tbody><tr><td>alpha</td><td>beta</td></tr></tbody></table>')[0];

        $this->assertStringContainsString('alpha', $block->text);
        $this->assertStringContainsString('beta', $block->text);
        $this->assertStringNotContainsString('alphabeta', $block->text);
    }

    // ------------------------------------------------------------------
    // A ticked task item
    // ------------------------------------------------------------------

    /**
     * The tick has to reach the signature, not just the attribute map: the
     * differ decides "unchanged" on the signature alone, so an attribute it
     * never compares reports nothing.
     */
    public function test_ticking_a_task_item_changes_its_signature_but_not_its_match_key(): void
    {
        $list = '<ul data-type="taskList"><li data-type="taskItem" data-checked="%s">buy milk</li></ul>';

        $unticked = $this->tokenizer->tokenize(sprintf($list, 'false'))[0];
        $ticked = $this->tokenizer->tokenize(sprintf($list, 'true'))[0];

        $this->assertNotSame($unticked->signature, $ticked->signature);
        $this->assertSame($unticked->matchKey(), $ticked->matchKey());
    }
}
