<?php

namespace Tests\Unit\Services;

use App\Enums\ChallengeState;
use App\Models\Challenge;
use App\Models\Project;
use App\Models\User;
use App\Models\WordCountSnapshot;
use App\Services\ChallengeProgress;
use App\Support\ChallengeStanding;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Score a challenge against the shipped snapshot rows. */
class ChallengeProgressTest extends TestCase
{
    use RefreshDatabase;

    private ChallengeProgress $progress;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->progress = app(ChallengeProgress::class);
        // A non-UTC owner, so a day count that leaned on the server clock shows up.
        $this->user = User::factory()->create(['timezone' => 'Pacific/Auckland']);
        $this->project = Project::factory()->for($this->user)->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function snapshot(string $date, int $total): void
    {
        WordCountSnapshot::factory()->for($this->project)->create([
            'recorded_on' => $date,
            'word_count' => $total,
        ]);
    }

    /** Freeze the clock, and answer with the local day of the owner. */
    private function today(string $date): CarbonImmutable
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($date.' 09:00', $this->user->timezone));

        return WriterDay::for($this->user);
    }

    private function fixed(string $from, string $to, int $target): Challenge
    {
        return Challenge::factory()->for($this->project)->create([
            'starts_on' => $from,
            'ends_on' => $to,
            'target_words' => $target,
        ]);
    }

    private function monthly(string $from, int $target, ?string $endsOn = null): Challenge
    {
        return Challenge::factory()->for($this->project)->monthly()->create([
            'starts_on' => $from,
            'ends_on' => $endsOn,
            'target_words' => $target,
        ]);
    }

    private function standing(Challenge $challenge, string $today): ChallengeStanding
    {
        return $this->progress->standing($challenge, $this->today($today));
    }

    public function test_a_window_entirely_before_the_first_snapshot_wrote_nothing(): void
    {
        $this->snapshot('2026-05-01', 4000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-06-01');

        $this->assertSame(0, $standing->written);
        $this->assertSame(ChallengeState::Finished, $standing->state);
        $this->assertFalse($standing->met);
    }

    public function test_a_window_opening_before_the_first_snapshot_counts_from_zero(): void
    {
        $this->snapshot('2026-03-05', 1200);
        $this->snapshot('2026-03-08', 3000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-20');

        $this->assertSame(3000, $standing->written);
    }

    public function test_a_window_opening_mid_history_uses_the_day_before_as_its_baseline(): void
    {
        $this->snapshot('2026-02-28', 10000);
        $this->snapshot('2026-03-01', 10500);
        $this->snapshot('2026-03-02', 11200);
        $challenge = $this->fixed('2026-03-01', '2026-03-02', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame(1200, $standing->written);
        $this->assertSame([500, 1200], $standing->dailyTotals->all());
    }

    public function test_a_cut_day_lowers_the_total_and_steps_the_line_back(): void
    {
        $this->snapshot('2026-03-01', 1000);
        $this->snapshot('2026-03-02', 700);
        $this->snapshot('2026-03-03', 900);
        $challenge = $this->fixed('2026-03-01', '2026-03-03', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame(900, $standing->written);
        $this->assertSame([1000, 700, 900], $standing->dailyTotals->all());
    }

    public function test_a_net_cut_across_the_window_reads_negative(): void
    {
        $this->snapshot('2026-02-28', 5000);
        $this->snapshot('2026-03-02', 2700);
        $challenge = $this->fixed('2026-03-01', '2026-03-03', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame(-2300, $standing->written);
        // A cut adds to what is left: the target moves no closer.
        $this->assertSame(7300, $standing->remaining);
    }

    public function test_gap_days_write_nothing(): void
    {
        $this->snapshot('2026-03-01', 500);
        $this->snapshot('2026-03-04', 800);
        $challenge = $this->fixed('2026-03-01', '2026-03-04', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame([500, 0, 0, 300], $standing->days->map->written->all());
        $this->assertSame([500, 500, 500, 800], $standing->dailyTotals->all());
    }

    public function test_par_is_zero_on_the_morning_of_the_first_day(): void
    {
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-01');

        $this->assertSame(ChallengeState::Running, $standing->state);
        $this->assertSame(1, $standing->elapsedDays);
        $this->assertSame(0, $standing->parDays);
        $this->assertSame(0, $standing->par);
        $this->assertSame(10, $standing->daysLeft);
    }

    public function test_par_on_the_last_day_is_one_day_short_of_the_target(): void
    {
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame(10, $standing->elapsedDays);
        $this->assertSame(9, $standing->parDays);
        $this->assertSame(4500, $standing->par);
        $this->assertSame(1, $standing->daysLeft);
    }

    public function test_par_equals_the_target_once_the_window_is_finished(): void
    {
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-11');

        $this->assertSame(ChallengeState::Finished, $standing->state);
        $this->assertSame(10, $standing->elapsedDays);
        $this->assertSame(5000, $standing->par);
        $this->assertSame(0, $standing->daysLeft);
        $this->assertNull($standing->perDayNeeded);
    }

    public function test_an_upcoming_challenge_has_no_elapsed_days_and_no_verdict(): void
    {
        $challenge = $this->fixed('2026-04-01', '2026-04-10', 5000);

        $standing = $this->standing($challenge, '2026-03-20');

        $this->assertSame(ChallengeState::Upcoming, $standing->state);
        $this->assertSame(0, $standing->elapsedDays);
        $this->assertSame(0, $standing->par);
        $this->assertSame(0, $standing->daysLeft);
        $this->assertNull($standing->perDayNeeded);
        $this->assertNull($standing->met);
    }

    public function test_written_equal_to_the_target_counts_as_met(): void
    {
        $this->snapshot('2026-03-05', 5000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-11');

        $this->assertTrue($standing->met);
        $this->assertSame(0, $standing->remaining);
    }

    public function test_overshooting_the_target_keeps_counting(): void
    {
        $this->snapshot('2026-03-05', 61000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 50000);

        $standing = $this->standing($challenge, '2026-03-11');

        $this->assertSame(61000, $standing->written);
        $this->assertSame(0, $standing->remaining);
        $this->assertTrue($standing->met);
    }

    public function test_the_last_day_needs_everything_that_is_left(): void
    {
        $this->snapshot('2026-03-05', 4000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-10');

        $this->assertSame(1, $standing->daysLeft);
        $this->assertSame(1000, $standing->remaining);
        $this->assertSame(1000, $standing->perDayNeeded);
    }

    public function test_nothing_left_to_write_needs_zero_a_day(): void
    {
        $this->snapshot('2026-03-02', 5000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-03');

        $this->assertSame(0, $standing->remaining);
        $this->assertSame(0, $standing->perDayNeeded);
    }

    public function test_delta_reports_how_far_ahead_or_behind_the_writer_is(): void
    {
        $this->snapshot('2026-03-04', 3000);
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $standing = $this->standing($challenge, '2026-03-05');

        // Four finished days of a ten-day 5,000-word window: par 2,000.
        $this->assertSame(2000, $standing->par);
        $this->assertSame(1000, $standing->delta);
    }

    public function test_the_par_line_holds_the_end_of_day_figure_for_every_day(): void
    {
        $challenge = $this->fixed('2026-03-01', '2026-03-05', 5000);

        $standing = $this->standing($challenge, '2026-03-01');

        $this->assertSame([1000, 2000, 3000, 4000, 5000], $standing->parTotals->all());
    }

    public function test_a_running_window_flattens_after_today(): void
    {
        $this->snapshot('2026-03-02', 900);
        $challenge = $this->fixed('2026-03-01', '2026-03-05', 5000);

        $standing = $this->standing($challenge, '2026-03-03');

        $this->assertSame([0, 900, 900, 900, 900], $standing->dailyTotals->all());
    }

    public function test_a_monthly_challenge_is_scored_against_the_current_month(): void
    {
        $this->snapshot('2026-03-31', 1000);
        $this->snapshot('2026-04-10', 9000);
        $challenge = $this->monthly('2026-01-01', 20000);

        $standing = $this->standing($challenge, '2026-04-15');

        $this->assertSame('2026-04-01', $standing->window->from->toDateString());
        $this->assertSame(8000, $standing->written);
        $this->assertSame(30, $standing->totalDays);
    }

    public function test_a_fixed_challenge_reports_itself_as_past_only_once_finished(): void
    {
        $challenge = $this->fixed('2026-03-01', '2026-03-10', 5000);

        $this->assertCount(0, $this->progress->pastOccurrences($challenge, $this->today('2026-03-10')));
        $this->assertCount(1, $this->progress->pastOccurrences($challenge, $this->today('2026-03-11')));
    }

    public function test_a_monthly_challenge_reports_one_entry_for_each_completed_month(): void
    {
        $this->snapshot('2026-01-15', 1000);
        $this->snapshot('2026-02-15', 4000);
        $challenge = $this->monthly('2026-01-01', 2000);

        $past = $this->progress->pastOccurrences($challenge, $this->today('2026-03-05'));

        $this->assertSame(['2026-02-01', '2026-01-01'], $past->map(
            fn (ChallengeStanding $standing) => $standing->window->from->toDateString(),
        )->all());
        $this->assertSame([3000, 1000], $past->map->written->all());
        $this->assertTrue($past->first()->met);
        $this->assertFalse($past->last()->met);
    }

    public function test_completed_months_stop_at_the_end_date(): void
    {
        $challenge = $this->monthly('2026-01-01', 2000, '2026-02-20');

        $past = $this->progress->pastOccurrences($challenge, $this->today('2026-06-05'));

        $this->assertSame(['2026-02-01', '2026-01-01'], $past->map(
            fn (ChallengeStanding $standing) => $standing->window->from->toDateString(),
        )->all());
    }

    public function test_completed_months_cap_at_twelve_newest_first_in_one_series_query(): void
    {
        $this->snapshot('2026-06-15', 7000);
        $challenge = $this->monthly('2024-01-01', 2000);

        $today = $this->today('2026-07-05');
        $challenge->load('project');

        DB::enableQueryLog();
        $past = $this->progress->pastOccurrences($challenge, $today);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(12, $past);
        $this->assertSame('2026-06-01', $past->first()->window->from->toDateString());
        $this->assertSame('2025-07-01', $past->last()->window->from->toDateString());
        // One series call: the rows in the range, and the row before it.
        $this->assertCount(2, $queries);
    }

    public function test_a_monthly_challenge_in_its_first_month_has_no_past(): void
    {
        $challenge = $this->monthly('2026-03-01', 2000);

        $this->assertCount(0, $this->progress->pastOccurrences($challenge, $this->today('2026-03-20')));
    }
}
