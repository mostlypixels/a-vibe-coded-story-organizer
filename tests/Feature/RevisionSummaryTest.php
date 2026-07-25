<?php

namespace Tests\Feature;

use App\Enums\FieldKind;
use App\Enums\RevisionOrigin;
use App\Models\Act;
use App\Models\Scene;
use App\Models\User;
use App\Services\RevisionRecorder;
use App\Services\RevisionSummarizer;
use App\Support\RevisionSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 9 — every revision row carries its own summary, written at the moment
 * the row is (expanded/data-model.md, *Who writes `summary_html` /
 * `change_count`*).
 *
 * The point of storing them is that a page of history renders without diffing
 * anything, so what matters here is that the columns are correct at every way
 * *in*: a fresh insert, a coalescing autosave that rewrites a row it already
 * summarised, a baseline with nothing before it, and an import replaying a
 * whole history at once.
 */
class RevisionSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function recorder(): RevisionRecorder
    {
        return app(RevisionRecorder::class);
    }

    public function test_a_recorded_change_stores_a_summary_and_a_matching_count(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->create(['description' => '<p>The ferry left at dawn.</p>']);

        $revision = $this->recorder()->record(
            $act,
            'description',
            '<p>The ferry slipped away at dawn.</p>',
            $user,
            RevisionOrigin::Manual,
        );

        $this->assertSame(1, $revision->change_count);
        $this->assertStringContainsString('<del', $revision->summary_html);
        $this->assertStringContainsString('left', $revision->summary_html);
        $this->assertStringContainsString('<ins', $revision->summary_html);
        $this->assertStringContainsString('slipped away', $revision->summary_html);
    }

    public function test_a_coalescing_autosave_refreshes_the_summary_it_already_wrote(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create(['contents' => 'She waited.']);
        $recorder = $this->recorder();

        $first = $recorder->record($scene, 'contents', 'She waited quietly.', $user, RevisionOrigin::Automatic);

        $this->assertStringContainsString('quietly.', $first->summary_html);

        // Still inside Scene.contents' 60-second coalescing window, so this
        // overwrites the row above rather than adding one — and the summary it
        // wrote a moment ago now describes a diff that no longer exists.
        $this->travel(10)->seconds();
        $second = $recorder->record($scene, 'contents', 'She waited impatiently.', $user, RevisionOrigin::Automatic);

        $this->assertTrue($first->is($second));

        $row = $first->fresh();
        $this->assertStringContainsString('impatiently.', $row->summary_html);
        $this->assertStringNotContainsString('quietly.', $row->summary_html);
        $this->assertSame(1, $row->change_count);
    }

    public function test_a_coalescing_autosave_summarizes_against_the_row_before_it_not_itself(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create(['contents' => 'She waited.']);
        $recorder = $this->recorder();

        // Three writes into one coalesced row. Each one must be summarised
        // against the baseline ("She waited."), never against the value the
        // previous write left in the row it is overwriting — otherwise the
        // final summary would describe the last keystroke instead of the burst.
        $recorder->record($scene, 'contents', 'She waited a while.', $user, RevisionOrigin::Automatic);
        $recorder->record($scene, 'contents', 'She waited a long while.', $user, RevisionOrigin::Automatic);
        $row = $recorder->record($scene, 'contents', 'She waited a very long while.', $user, RevisionOrigin::Automatic);

        $this->assertStringContainsString('very long while.', $row->summary_html);
        $this->assertStringContainsString('waited', $row->summary_html);
    }

    public function test_a_baseline_row_stores_no_summary(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->create(['description' => '<p>Pre-existing</p>']);

        $this->recorder()->record($act, 'description', '<p>Edited</p>', $user, RevisionOrigin::Manual);

        $baseline = $act->revisions()->where('origin', RevisionOrigin::Baseline)->sole();

        $this->assertNull($baseline->summary_html);
        $this->assertSame(0, $baseline->change_count);
    }

    public function test_a_forty_hunk_change_stores_a_bounded_summary_and_the_whole_count(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->create(['description' => $this->alternatingParagraphs('Mira walked on.')]);

        $revision = $this->recorder()->record(
            $act,
            'description',
            $this->alternatingParagraphs('Elin walked on.'),
            $user,
            RevisionOrigin::Manual,
        );

        // The row reports every hunk — that is what "and 39 more changes" is
        // built from (rendered in task 13) — while showing only the first.
        $this->assertSame(40, $revision->change_count);
        $this->assertLessThanOrEqual(
            (int) config('revisions.summary.max_length'),
            mb_strlen(strip_tags($revision->summary_html)),
        );
    }

    public function test_a_stored_summary_escapes_the_content_it_describes(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create(['contents' => 'Salt & pepper']);

        $revision = $this->recorder()->record(
            $scene,
            'contents',
            'Salt & pepper <script>alert(1)</script>',
            $user,
            RevisionOrigin::Manual,
        );

        $this->assertStringNotContainsString('<script>', $revision->summary_html);
        $this->assertStringContainsString('&lt;script&gt;', $revision->summary_html);
        $this->assertStringContainsString('&amp;', $revision->summary_html);
    }

    public function test_a_failing_summarizer_does_not_cost_the_writer_her_save(): void
    {
        $user = User::factory()->create();
        $act = Act::factory()->create(['description' => '<p>Before</p>']);

        $this->mock(RevisionSummarizer::class)
            ->shouldReceive('summarize')
            ->andThrow(new RuntimeException('the diff layer fell over'));

        $revision = app(RevisionRecorder::class)->record(
            $act,
            'description',
            '<p>After</p>',
            $user,
            RevisionOrigin::Manual,
        );

        // The text is what matters; the summary is decoration the row can live
        // without.
        $this->assertSame('<p>After</p>', $revision->value);
        $this->assertNull($revision->summary_html);
        $this->assertSame(0, $revision->change_count);
    }

    public function test_the_summarizer_is_asked_for_the_fields_own_kind(): void
    {
        $user = User::factory()->create();
        $scene = Scene::factory()->create(['contents' => 'She waited.']);

        // Scene.contents is Markdown, not rich HTML. Routing it as Rich would
        // tokenize away the very characters the writer typed.
        $this->mock(RevisionSummarizer::class)
            ->shouldReceive('summarize')
            ->once()
            ->withArgs(fn (FieldKind $kind): bool => $kind === FieldKind::Markdown)
            ->andReturn(new RevisionSummary('<ins>ok</ins>', 1));

        app(RevisionRecorder::class)->record($scene, 'contents', 'She waited **quietly**.', $user, RevisionOrigin::Manual);
    }

    /**
     * Forty changed paragraphs, each separated by one that does not change, so
     * they stay forty distinct hunks instead of merging into one long run.
     */
    private function alternatingParagraphs(string $text): string
    {
        $html = '';

        for ($index = 0; $index < 40; $index++) {
            $html .= "<p>{$text}</p><p>The tide came in, number {$index}.</p>";
        }

        return $html;
    }
}
