<?php

namespace Tests\Unit\Support;

use App\Enums\ChallengeState;
use App\Models\Challenge;
use App\Support\ChallengeWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_window_on_the_first_of_a_31_day_month(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-01-01']);
        $today = CarbonImmutable::parse('2026-01-01');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-01-01', $window->from->toDateString());
        $this->assertSame('2026-01-31', $window->to->toDateString());
        $this->assertSame(31, $window->totalDays());
    }

    public function test_monthly_window_on_the_last_day_of_a_30_day_month(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-01-01']);
        $today = CarbonImmutable::parse('2026-04-30');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-04-01', $window->from->toDateString());
        $this->assertSame('2026-04-30', $window->to->toDateString());
        $this->assertSame(30, $window->totalDays());
    }

    public function test_monthly_window_on_the_last_day_of_february_in_a_leap_year(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-01-01']);
        $today = CarbonImmutable::parse('2028-02-29');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2028-02-01', $window->from->toDateString());
        $this->assertSame('2028-02-29', $window->to->toDateString());
        $this->assertSame(29, $window->totalDays());
    }

    public function test_monthly_window_on_the_last_day_of_february_in_a_non_leap_year(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-01-01']);
        $today = CarbonImmutable::parse('2026-02-28');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-02-01', $window->from->toDateString());
        $this->assertSame('2026-02-28', $window->to->toDateString());
        $this->assertSame(28, $window->totalDays());
    }

    public function test_monthly_challenge_starting_in_a_future_month_is_upcoming_and_windowed_on_that_month(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-05-15']);
        $today = CarbonImmutable::parse('2026-04-20');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-05-01', $window->from->toDateString());
        $this->assertSame('2026-05-31', $window->to->toDateString());
        $this->assertSame(ChallengeState::Upcoming, $window->state($today));
    }

    public function test_monthly_first_month_is_not_clipped_to_starts_on(): void
    {
        $challenge = Challenge::factory()->monthly()->make(['starts_on' => '2026-03-10']);
        $today = CarbonImmutable::parse('2026-03-10');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-03-01', $window->from->toDateString());
        $this->assertSame('2026-03-31', $window->to->toDateString());
    }

    public function test_monthly_challenge_past_its_ends_on_month_is_finished_and_windowed_on_that_month(): void
    {
        $challenge = Challenge::factory()->monthly()->make([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-03-15',
        ]);
        $today = CarbonImmutable::parse('2026-06-01');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame('2026-03-01', $window->from->toDateString());
        $this->assertSame('2026-03-31', $window->to->toDateString());
        $this->assertSame(ChallengeState::Finished, $window->state($today));
    }

    public function test_fixed_window_comes_back_unchanged_before_during_and_after(): void
    {
        $challenge = Challenge::factory()->make([
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-01-20',
        ]);

        $window = ChallengeWindow::for($challenge, CarbonImmutable::parse('2026-01-05'));

        $this->assertSame('2026-01-10', $window->from->toDateString());
        $this->assertSame('2026-01-20', $window->to->toDateString());
        $this->assertSame(11, $window->totalDays());

        $this->assertSame(ChallengeState::Upcoming, $window->state(CarbonImmutable::parse('2026-01-05')));
        $this->assertSame(ChallengeState::Running, $window->state(CarbonImmutable::parse('2026-01-15')));
        $this->assertSame(ChallengeState::Finished, $window->state(CarbonImmutable::parse('2026-01-25')));
    }

    public function test_last_day_is_running_next_day_is_finished(): void
    {
        $challenge = Challenge::factory()->make([
            'starts_on' => '2026-01-10',
            'ends_on' => '2026-01-20',
        ]);

        $window = ChallengeWindow::for($challenge, CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(ChallengeState::Running, $window->state(CarbonImmutable::parse('2026-01-20')));
        $this->assertSame(ChallengeState::Finished, $window->state(CarbonImmutable::parse('2026-01-21')));
    }

    public function test_total_days_is_correct_across_a_daylight_saving_change(): void
    {
        // America/Los_Angeles springs forward on 2026-03-08: a naive
        // instant-based diff would undercount this window by one day.
        $challenge = Challenge::factory()->make([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
        ]);

        $today = CarbonImmutable::parse('2026-03-01 00:00:00', 'America/Los_Angeles');

        $window = ChallengeWindow::for($challenge, $today);

        $this->assertSame(31, $window->totalDays());
    }
}
