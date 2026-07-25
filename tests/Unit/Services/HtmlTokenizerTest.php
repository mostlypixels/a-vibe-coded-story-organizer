<?php

namespace Tests\Unit\Services;

use App\Services\Diff\HtmlTokenizer;
use App\Support\HtmlBlock;
use App\Support\InlineToken;
use Tests\TestCase;

/**
 * Task 4 — App\Services\Diff\HtmlTokenizer, the first stage of the in-house
 * visual differ: purified rich HTML in, a flat list of HtmlBlocks out.
 *
 * Nothing calls it yet; it is proven here in isolation before any page depends
 * on it, because the diff engine is the riskiest part of the feature.
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
}
