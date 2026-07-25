<?php

namespace Tests\Unit\Services;

use App\Enums\DiffChange;
use App\Services\Diff\HtmlTokenizer;
use App\Services\Diff\VisualHtmlDiffer;
use App\Support\DiffBlock;
use App\Support\DiffSpan;
use App\Support\VisualDiff;
use Tests\TestCase;

/**
 * Task 5 — App\Services\Diff\VisualHtmlDiffer: block diff first, word diff
 * inside the blocks that changed. Structure only; rendering is task 6.
 */
class VisualHtmlDifferTest extends TestCase
{
    private VisualHtmlDiffer $differ;

    protected function setUp(): void
    {
        parent::setUp();

        $this->differ = new VisualHtmlDiffer(new HtmlTokenizer);
    }

    /** @return list<DiffChange> */
    private function changes(VisualDiff $diff): array
    {
        return array_map(fn (DiffBlock $block): DiffChange => $block->change, $diff->blocks);
    }

    /** @return list<array{0: string, 1: string}> */
    private function spans(DiffBlock $block): array
    {
        return array_map(fn (DiffSpan $span): array => [$span->change->value, $span->text()], $block->spans);
    }

    // ---------------------------------------------------------------------
    // The word-level pass
    // ---------------------------------------------------------------------

    public function test_a_word_changed_inside_a_paragraph_leaves_the_rest_of_it_alone(): void
    {
        $diff = $this->differ->diff('<p>The cat sat quietly</p>', '<p>The dog sat quietly</p>');

        $this->assertSame([DiffChange::Replaced], $this->changes($diff));
        $this->assertSame([
            ['unchanged', 'The'],
            ['removed', 'cat'],
            ['inserted', 'dog'],
            ['unchanged', 'sat quietly'],
        ], $this->spans($diff->blocks[0]));
    }

    public function test_only_the_changed_paragraph_of_several_is_marked(): void
    {
        $diff = $this->differ->diff(
            '<p>One</p><p>Two</p><p>Three</p>',
            '<p>One</p><p>Two changed</p><p>Three</p>',
        );

        $this->assertSame(
            [DiffChange::Unchanged, DiffChange::Replaced, DiffChange::Unchanged],
            $this->changes($diff),
        );
        $this->assertSame(1, $diff->changeCount);
    }

    // ---------------------------------------------------------------------
    // Formatting-only changes
    // ---------------------------------------------------------------------

    public function test_bolding_a_word_without_rewording_reports_a_formatting_change(): void
    {
        $diff = $this->differ->diff('<p>The cat sat</p>', '<p>The <strong>cat</strong> sat</p>');

        $this->assertSame([DiffChange::FormattingChanged], $this->changes($diff));
        $this->assertSame(['strong'], $diff->blocks[0]->marksAdded);
        $this->assertSame([], $diff->blocks[0]->marksRemoved);

        // It counts: a save that only bolded a word did change something, and a
        // history row claiming "no changes" for it would be a lie.
        $this->assertSame(1, $diff->changeCount);
    }

    public function test_removing_a_mark_is_reported_as_a_removed_mark(): void
    {
        $diff = $this->differ->diff('<p>The <em>cat</em> sat</p>', '<p>The cat sat</p>');

        $this->assertSame([DiffChange::FormattingChanged], $this->changes($diff));
        $this->assertSame(['em'], $diff->blocks[0]->marksRemoved);
    }

    // ---------------------------------------------------------------------
    // Whole blocks
    // ---------------------------------------------------------------------

    public function test_an_inserted_paragraph_is_a_whole_block_insert(): void
    {
        $diff = $this->differ->diff('<p>One</p>', '<p>One</p><p>Two</p>');

        $this->assertSame([DiffChange::Unchanged, DiffChange::Inserted], $this->changes($diff));
        $this->assertSame('Two', $diff->blocks[1]->block->text);
        $this->assertSame([], $diff->blocks[1]->spans);
    }

    public function test_a_removed_paragraph_keeps_its_old_text_for_display(): void
    {
        $diff = $this->differ->diff('<p>One</p><p>Two</p>', '<p>One</p>');

        $this->assertSame([DiffChange::Unchanged, DiffChange::Removed], $this->changes($diff));
        $this->assertSame('Two', $diff->blocks[1]->block->text);
    }

    public function test_a_field_that_did_not_exist_yet_reads_as_a_whole_insert(): void
    {
        $diff = $this->differ->diff(null, '<p>First words</p>');

        $this->assertSame([DiffChange::Inserted], $this->changes($diff));
        $this->assertSame(1, $diff->changeCount);
    }

    public function test_a_moved_paragraph_reports_as_a_removal_plus_an_insertion(): void
    {
        // The documented limitation: move detection would mean matching blocks
        // across the whole document, which costs more than it buys for prose.
        // Asserted so it stays a decision rather than becoming a surprise.
        $diff = $this->differ->diff(
            '<p>Alpha</p><p>Beta</p><p>Gamma</p>',
            '<p>Beta</p><p>Gamma</p><p>Alpha</p>',
        );

        $this->assertSame(
            [DiffChange::Removed, DiffChange::Unchanged, DiffChange::Unchanged, DiffChange::Inserted],
            $this->changes($diff),
        );
    }

    public function test_a_paragraph_promoted_to_a_heading_is_a_real_change(): void
    {
        // Same words, different block: matchKey() carries the tag, so this does
        // not silently read as unchanged.
        $diff = $this->differ->diff('<p>Chapter one</p>', '<h2>Chapter one</h2>');

        $this->assertNotSame([DiffChange::Unchanged], $this->changes($diff));
        $this->assertGreaterThan(0, $diff->changeCount);
    }

    // ---------------------------------------------------------------------
    // Counting and the cap
    // ---------------------------------------------------------------------

    public function test_identical_values_produce_no_changes(): void
    {
        $diff = $this->differ->diff('<p>One</p><p>Two</p>', '<p>One</p><p>Two</p>');

        $this->assertSame([DiffChange::Unchanged, DiffChange::Unchanged], $this->changes($diff));
        $this->assertSame(0, $diff->changeCount);
        $this->assertFalse($diff->hasChanges());
        $this->assertSame([], $diff->changedBlocks());
    }

    public function test_the_hunk_count_counts_runs_of_changed_blocks_not_blocks(): void
    {
        // Two separate edits, far apart, with an untouched paragraph between:
        // two hunks — even though the first of them replaced two blocks at once.
        $diff = $this->differ->diff(
            '<p>One</p><p>Two</p><p>Middle</p><p>Three</p>',
            '<p>One edited</p><p>Two edited</p><p>Middle</p><p>Three edited</p>',
        );

        $this->assertSame(2, $diff->changeCount);
        $this->assertCount(3, $diff->changedBlocks());
    }

    public function test_a_pair_over_the_complexity_cap_degrades_to_block_level(): void
    {
        config()->set('revisions.diff.max_word_complexity', 4);

        $diff = $this->differ->diff(
            '<p>one two three four five</p>',
            '<p>six seven eight nine ten</p>',
        );

        // 5 * 5 = 25 tokens' worth of work, over the (deliberately tiny) cap —
        // so the word-level pass is skipped entirely rather than run.
        $this->assertSame([DiffChange::Removed, DiffChange::Inserted], $this->changes($diff));
        $this->assertSame([], $diff->blocks[0]->spans);
    }

    public function test_under_the_cap_the_same_pair_still_gets_word_spans(): void
    {
        config()->set('revisions.diff.max_word_complexity', 2_000_000);

        $diff = $this->differ->diff(
            '<p>one two three four five</p>',
            '<p>six seven eight nine ten</p>',
        );

        $this->assertSame([DiffChange::Replaced], $this->changes($diff));
        $this->assertNotSame([], $diff->blocks[0]->spans);
    }
}
