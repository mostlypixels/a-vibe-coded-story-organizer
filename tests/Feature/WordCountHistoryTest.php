<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use App\Services\WordCountHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read path: snapshot rows become one entry per calendar day, with the
 * daily figure derived (expanded/architecture.md "The read path").
 *
 * The rules under test: a day with no row inherits the previous total and
 * wrote 0; the first day's figure comes from the row before the range; and
 * with no earlier row the previous total is 0, so a project's first writing
 * day counts in full.
 */
class WordCountHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(Project $project, string $date, int $total): void
    {
        WordCountSnapshot::factory()->for($project)->create([
            'recorded_on' => $date,
            'word_count' => $total,
        ]);
    }

    private function series(Project $project, string $from, string $to)
    {
        return app(WordCountHistory::class)->series(
            $project,
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
        );
    }

    public function test_consecutive_days_report_each_days_difference(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);
        $this->snapshot($project, '2026-03-02', 250);
        $this->snapshot($project, '2026-03-03', 400);

        $series = $this->series($project, '2026-03-01', '2026-03-03');

        $this->assertSame([100, 150, 150], $series->days->map->written->all());
        $this->assertSame([100, 250, 400], $series->days->map->total->all());
        $this->assertSame(400, $series->currentTotal());
        $this->assertSame(400, $series->writtenInRange());
    }

    public function test_a_gap_carries_the_previous_total_and_writes_nothing(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);
        $this->snapshot($project, '2026-03-05', 300);

        $series = $this->series($project, '2026-03-01', '2026-03-05');

        $this->assertCount(5, $series->days);
        $this->assertSame([100, 0, 0, 0, 200], $series->days->map->written->all());
        $this->assertSame([100, 100, 100, 100, 300], $series->days->map->total->all());
    }

    public function test_a_range_starting_mid_history_uses_the_row_before_the_range(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-02-27', 5_000);
        $this->snapshot($project, '2026-03-02', 5_400);

        $series = $this->series($project, '2026-03-01', '2026-03-02');

        // Without the preceding row, day one would open with a 5,400-word spike.
        $this->assertSame([0, 400], $series->days->map->written->all());
        $this->assertSame([5_000, 5_400], $series->days->map->total->all());
    }

    public function test_with_no_row_before_the_range_the_first_writing_day_counts_in_full(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-02', 800);

        $series = $this->series($project, '2026-03-01', '2026-03-03');

        $this->assertSame([0, 800, 0], $series->days->map->written->all());
        $this->assertSame(800, $series->writtenInRange());
    }

    public function test_a_day_that_lost_words_reports_a_negative_figure(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 1_000);
        $this->snapshot($project, '2026-03-02', 700);

        $series = $this->series($project, '2026-03-01', '2026-03-02');

        $this->assertSame([1_000, -300], $series->days->map->written->all());
        $this->assertSame(700, $series->writtenInRange());
    }

    public function test_a_project_with_no_snapshots_reports_zeros(): void
    {
        $project = Project::factory()->create();

        $series = $this->series($project, '2026-03-01', '2026-03-03');

        $this->assertCount(3, $series->days);
        $this->assertSame([0, 0, 0], $series->days->map->total->all());
        $this->assertSame(0, $series->writtenInRange());
        $this->assertSame(0, $series->currentTotal());
    }

    public function test_a_range_that_ends_before_it_starts_is_empty(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);

        $series = $this->series($project, '2026-03-05', '2026-03-01');

        $this->assertTrue($series->isEmpty());
        $this->assertSame(0, $series->currentTotal());
        $this->assertSame(0, $series->writtenInRange());
    }

    public function test_written_on_reports_a_day_and_returns_zero_outside_the_range(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);
        $this->snapshot($project, '2026-03-03', 450);

        $series = $this->series($project, '2026-03-01', '2026-03-03');

        $this->assertSame(350, $series->writtenOn(CarbonImmutable::parse('2026-03-03')));
        $this->assertSame(0, $series->writtenOn(CarbonImmutable::parse('2026-03-02')));
        $this->assertSame(0, $series->writtenOn(CarbonImmutable::parse('2026-04-01')));
    }

    public function test_another_projects_rows_never_leak_into_the_series(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);
        $this->snapshot($other, '2026-03-01', 9_999);
        $this->snapshot($other, '2026-02-01', 5_000);

        $series = $this->series($project, '2026-03-01', '2026-03-01');

        $this->assertSame([100], $series->days->map->written->all());
    }

    public function test_the_series_costs_two_queries_whatever_the_range_length(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 100);

        DB::enableQueryLog();
        $this->series($project, '2026-01-01', '2026-12-31');

        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }

    // --- The daily-goal streak ---------------------------------------------

    private function streak(Project $project, int $goal, string $today): int
    {
        return app(WordCountHistory::class)->currentStreak(
            $project,
            $goal,
            CarbonImmutable::parse($today),
        );
    }

    public function test_consecutive_days_over_the_goal_count_as_a_streak(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);
        $this->snapshot($project, '2026-03-02', 1_100);
        $this->snapshot($project, '2026-03-03', 1_700);

        $this->assertSame(3, $this->streak($project, 500, '2026-03-03'));
    }

    public function test_a_day_below_the_goal_ends_the_streak(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);
        $this->snapshot($project, '2026-03-02', 600);   // 100 written
        $this->snapshot($project, '2026-03-03', 1_200);

        $this->assertSame(1, $this->streak($project, 500, '2026-03-03'));
    }

    public function test_a_day_with_no_row_ends_the_streak(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);
        // Nothing on 2026-03-02: no row means no save, which means no words.
        $this->snapshot($project, '2026-03-03', 1_000);

        $this->assertSame(1, $this->streak($project, 500, '2026-03-03'));
    }

    public function test_today_extends_a_streak_but_does_not_break_it(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);
        $this->snapshot($project, '2026-03-02', 1_000);

        // Nothing written today yet — yesterday's streak still stands.
        $this->assertSame(2, $this->streak($project, 500, '2026-03-03'));

        // A partial day today does not break it either.
        $this->snapshot($project, '2026-03-03', 1_100);
        $this->assertSame(2, $this->streak($project, 500, '2026-03-03'));
    }

    public function test_a_day_that_cuts_words_ends_the_streak(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);
        $this->snapshot($project, '2026-03-02', 1_000);
        $this->snapshot($project, '2026-03-03', 800);    // -200 written

        $this->assertSame(0, $this->streak($project, 500, '2026-03-04'));
    }

    public function test_the_first_row_ever_counts_in_full(): void
    {
        $project = Project::factory()->create();
        $this->snapshot($project, '2026-03-01', 500);

        $this->assertSame(1, $this->streak($project, 500, '2026-03-01'));
    }

    public function test_another_projects_rows_never_count(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $this->snapshot($project, '2026-03-02', 600);
        $this->snapshot($other, '2026-03-01', 9_999);

        $this->assertSame(1, $this->streak($project, 500, '2026-03-02'));
    }

    public function test_the_streak_costs_one_query_however_long_it_runs(): void
    {
        $project = Project::factory()->create();

        for ($day = 1; $day <= 31; $day++) {
            $this->snapshot($project, '2026-03-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), $day * 500);
        }

        DB::enableQueryLog();
        $streak = $this->streak($project, 500, '2026-03-31');

        $this->assertSame(31, $streak);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
