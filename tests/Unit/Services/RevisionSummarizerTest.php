<?php

namespace Tests\Unit\Services;

use App\Enums\FieldKind;
use App\Services\RevisionSummarizer;
use Tests\TestCase;

/**
 * App\Services\RevisionSummarizer — the one-line "what changed" a history row
 * shows, computed at write time (expanded/diffing.md, *Summaries*).
 *
 * The tests that matter here are about the *bound*: a summary has to stay
 * readable no matter how large the change was, without ever cutting the change
 * itself out of the picture or leaving a marker element hanging open.
 */
class RevisionSummarizerTest extends TestCase
{
    private RevisionSummarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->summarizer = $this->app->make(RevisionSummarizer::class);
    }

    public function test_a_one_word_change_is_summarized_with_both_markers(): void
    {
        $summary = $this->summarizer->summarize(
            FieldKind::Rich,
            '<p>The ferry left at dawn.</p>',
            '<p>The ferry slipped at dawn.</p>',
        );

        $this->assertSame(1, $summary->changeCount);
        $this->assertStringContainsString('<del', $summary->summaryHtml);
        $this->assertStringContainsString('left', $summary->summaryHtml);
        $this->assertStringContainsString('<ins', $summary->summaryHtml);
        $this->assertStringContainsString('slipped', $summary->summaryHtml);
        // Short enough to keep its surroundings, so the change reads in context.
        $this->assertStringContainsString('ferry', $summary->summaryHtml);
        $this->assertStringContainsString('dawn.', $summary->summaryHtml);
    }

    public function test_a_forty_hunk_find_and_replace_counts_every_hunk_but_summarizes_within_the_budget(): void
    {
        // Forty renamed paragraphs, each separated from the next by one that did
        // not change — so they are forty distinct hunks rather than one long
        // contiguous run, which is the shape a find-and-replace really takes.
        $old = $this->alternatingParagraphs('Mira walked on.', 40);
        $new = $this->alternatingParagraphs('Elin walked on.', 40);

        $summary = $this->summarizer->summarize(FieldKind::Rich, $old, $new);

        $this->assertSame(40, $summary->changeCount);
        $this->assertLessThanOrEqual(
            (int) config('revisions.summary.max_length'),
            mb_strlen($this->visibleText($summary->summaryHtml)),
        );
    }

    public function test_a_long_change_is_cut_without_leaving_a_marker_open(): void
    {
        $old = '<p>She waited.</p>';
        $new = '<p>She waited '.str_repeat('and waited ', 100).'for the ferry.</p>';

        $summary = $this->summarizer->summarize(FieldKind::Rich, $old, $new);

        $this->assertLessThanOrEqual(
            (int) config('revisions.summary.max_length'),
            mb_strlen($this->visibleText($summary->summaryHtml)),
        );
        $this->assertMarkersBalanced($summary->summaryHtml);
    }

    public function test_the_change_survives_the_cut_even_when_it_starts_late_in_the_paragraph(): void
    {
        // The changed word sits well past the budget from the start of the
        // block: spending the budget from the top down would cut it out, which
        // is the whole reason the summarizer spends it outward from the change.
        $lead = str_repeat('the tide came in and went out again ', 20);
        $old = "<p>{$lead}quietly.</p>";
        $new = "<p>{$lead}suddenly.</p>";

        $summary = $this->summarizer->summarize(FieldKind::Rich, $old, $new);

        $this->assertStringContainsString('suddenly.', $summary->summaryHtml);
        $this->assertStringContainsString('quietly.', $summary->summaryHtml);
        // Cut at the front, so the row says so rather than pretending to start
        // at the beginning of the paragraph.
        $this->assertStringStartsWith('…', $summary->summaryHtml);
        $this->assertLessThanOrEqual(
            (int) config('revisions.summary.max_length'),
            mb_strlen($this->visibleText($summary->summaryHtml)),
        );
    }

    public function test_content_is_escaped_in_the_summary(): void
    {
        // This value can reach the database through an import even though the
        // sanitizer strips it on write, so the summary must not trust it.
        $summary = $this->summarizer->summarize(
            FieldKind::Markdown,
            'Salt & pepper',
            'Salt & pepper <script>alert(1)</script>',
        );

        $this->assertStringNotContainsString('<script>', $summary->summaryHtml);
        $this->assertStringContainsString('&lt;script&gt;', $summary->summaryHtml);
        $this->assertStringContainsString('&amp;', $summary->summaryHtml);
    }

    public function test_a_markdown_field_is_summarized_from_its_raw_text(): void
    {
        $summary = $this->summarizer->summarize(
            FieldKind::Markdown,
            "# Chapter one\n\nShe waited.",
            "# Chapter two\n\nShe waited.",
        );

        $this->assertSame(1, $summary->changeCount);
        $this->assertStringContainsString('<del', $summary->summaryHtml);
        $this->assertStringContainsString('<ins', $summary->summaryHtml);
        // The heading marker is content here, not markup to be interpreted.
        $this->assertStringContainsString('#', $this->visibleText($summary->summaryHtml));
    }

    public function test_a_plain_field_is_summarized(): void
    {
        $summary = $this->summarizer->summarize(
            FieldKind::Plain,
            'All rights reserved.',
            'All rights reserved, 2026.',
        );

        $this->assertSame(1, $summary->changeCount);
        $this->assertStringContainsString('2026.', $summary->summaryHtml);
    }

    public function test_a_row_with_no_predecessor_summarizes_as_a_baseline(): void
    {
        $summary = $this->summarizer->summarize(FieldKind::Rich, null, '<p>The first draft.</p>');

        $this->assertNull($summary->summaryHtml);
        $this->assertSame(0, $summary->changeCount);
    }

    public function test_identical_values_invent_no_change(): void
    {
        $rich = $this->summarizer->summarize(FieldKind::Rich, '<p>Unchanged.</p>', '<p>Unchanged.</p>');
        $markdown = $this->summarizer->summarize(FieldKind::Markdown, 'Unchanged.', 'Unchanged.');

        $this->assertNull($rich->summaryHtml);
        $this->assertSame(0, $rich->changeCount);
        $this->assertNull($markdown->summaryHtml);
        $this->assertSame(0, $markdown->changeCount);
    }

    public function test_a_formatting_only_change_is_counted_and_points_at_the_paragraph(): void
    {
        // Nothing to strike out or underline — the words are identical — but the
        // save did change something, so the row must not read as "no changes".
        $summary = $this->summarizer->summarize(
            FieldKind::Rich,
            '<p>Hello world</p>',
            '<p>Hello <strong>world</strong></p>',
        );

        $this->assertSame(1, $summary->changeCount);
        $this->assertStringContainsString('Hello world', $this->visibleText($summary->summaryHtml));
    }

    public function test_a_wholly_new_paragraph_is_summarized_as_an_insertion(): void
    {
        $summary = $this->summarizer->summarize(
            FieldKind::Rich,
            '<p>She waited.</p>',
            '<p>She waited.</p><p>She counted the gulls.</p>',
        );

        $this->assertSame(1, $summary->changeCount);
        $this->assertStringContainsString('<ins', $summary->summaryHtml);
        $this->assertStringContainsString('counted the gulls.', $summary->summaryHtml);
    }

    /**
     * The summary's own budget is about what the reader sees, so the
     * visually-hidden marker labels the renderer adds do not count towards it.
     */
    private function visibleText(string $html): string
    {
        $withoutLabels = preg_replace('#<span class="sr-only">.*?</span>#', '', $html);

        return html_entity_decode(strip_tags($withoutLabels), ENT_QUOTES, 'UTF-8');
    }

    private function assertMarkersBalanced(string $html): void
    {
        foreach (['ins', 'del', 'span'] as $tag) {
            $this->assertSame(
                substr_count($html, "<{$tag}"),
                substr_count($html, "</{$tag}>"),
                "Unbalanced <{$tag}> in the summary — truncation split a marker.",
            );
        }
    }

    /**
     * `$count` copies of `$text`, each followed by a paragraph that is the same
     * in both versions and so keeps the changed ones from merging into one hunk.
     */
    private function alternatingParagraphs(string $text, int $count): string
    {
        $html = '';

        for ($index = 0; $index < $count; $index++) {
            $html .= "<p>{$text}</p><p>The tide came in, number {$index}.</p>";
        }

        return $html;
    }
}
