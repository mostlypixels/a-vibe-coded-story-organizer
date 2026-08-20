<?php

namespace App\Services;

use App\Enums\ChallengeRecurrence;
use App\Enums\ChallengeState;
use App\Models\Challenge;
use App\Support\ChallengeStanding;
use App\Support\ChallengeWindow;
use App\Support\DailyWordCount;
use App\Support\WordCountSeries;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Scores a challenge against the shipped word-count snapshots.
 *
 * Read-only: no progress is stored anywhere, so a standing is rebuilt from
 * the snapshot rows every time. Two queries per standing — the rows in the
 * window and the row before it (see {@see WordCountHistory}).
 *
 * `$today` is the caller's concern. Pass `WriterDay::for($project->user)`,
 * never `now()`.
 */
class ChallengeProgress
{
    /**
     * Completed months a monthly challenge can report, newest first.
     */
    public const PAST_LIMIT = 12;

    public function __construct(private readonly WordCountHistory $history) {}

    public function standing(Challenge $challenge, CarbonImmutable $today): ChallengeStanding
    {
        $window = ChallengeWindow::for($challenge, $today);

        return $this->score(
            $challenge,
            $window,
            $window->state($today),
            $today,
            $this->series($challenge, $window)->days,
        );
    }

    /**
     * Windows this challenge has already finished, newest first.
     *
     * A fixed challenge has one window, so it reports itself once it is
     * over. A monthly challenge reports one entry for each completed month,
     * all of them cut out of a **single** series: twelve months is twelve
     * slices of one array, not twelve queries.
     *
     * @return Collection<int, ChallengeStanding>
     */
    public function pastOccurrences(Challenge $challenge, CarbonImmutable $today, int $limit = self::PAST_LIMIT): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        if ($challenge->recurrence === ChallengeRecurrence::None) {
            $standing = $this->standing($challenge, $today);

            return $standing->state === ChallengeState::Finished ? collect([$standing]) : collect();
        }

        $months = $this->completedMonths($challenge, $today)->take(-$limit)->values();

        if ($months->isEmpty()) {
            return collect();
        }

        $windows = $months->map(fn (CarbonImmutable $month) => ChallengeWindow::for($challenge, $month));

        $days = $this->history->series(
            $challenge->project,
            $windows->first()->from,
            $windows->last()->to,
        )->days->keyBy(fn (DailyWordCount $day) => $day->date->toDateString());

        return $windows
            ->map(fn (ChallengeWindow $window) => $this->score(
                $challenge,
                $window,
                ChallengeState::Finished,
                $today,
                $this->slice($days, $window),
            ))
            ->reverse()
            ->values();
    }

    /**
     * Every whole month the challenge has already run, oldest first.
     *
     * A month counts as completed when it is behind `$today`'s month. An
     * `ends_on` stops the list after its own month, which is how a recurring
     * challenge is retired without deleting its record.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function completedMonths(Challenge $challenge, CarbonImmutable $today): Collection
    {
        $first = $this->monthOf($challenge->starts_on);
        $last = $this->monthOf($today)->subMonth();

        if ($challenge->ends_on !== null) {
            $endsMonth = $this->monthOf($challenge->ends_on);
            $last = $endsMonth->lessThan($last) ? $endsMonth : $last;
        }

        $months = collect();

        for ($month = $first; $month->lessThanOrEqualTo($last); $month = $month->addMonth()) {
            $months->push($month);
        }

        return $months;
    }

    /**
     * The days of one window, cut out of a series that covers several.
     *
     * @param  Collection<string, DailyWordCount>  $days  keyed `Y-m-d`
     * @return Collection<int, DailyWordCount>
     */
    private function slice(Collection $days, ChallengeWindow $window): Collection
    {
        $slice = collect();

        for ($date = $window->from; $date->lessThanOrEqualTo($window->to); $date = $date->addDay()) {
            $slice->push($days->get($date->toDateString()) ?? new DailyWordCount($date, 0, 0));
        }

        return $slice;
    }

    private function series(Challenge $challenge, ChallengeWindow $window): WordCountSeries
    {
        return $this->history->series($challenge->project, $window->from, $window->to);
    }

    /**
     * The arithmetic, over days that are already in hand.
     *
     * @param  Collection<int, DailyWordCount>  $days
     */
    private function score(
        Challenge $challenge,
        ChallengeWindow $window,
        ChallengeState $state,
        CarbonImmutable $today,
        Collection $days,
    ): ChallengeStanding {
        $series = new WordCountSeries($days);

        $totalDays = $window->totalDays();
        $target = (int) $challenge->target_words;

        $elapsedDays = $window->elapsedDays($today);

        // Par credits finished days only, so the morning of day 1 reads level.
        $parDays = match ($state) {
            ChallengeState::Upcoming => 0,
            ChallengeState::Running => $elapsedDays - 1,
            ChallengeState::Finished => $totalDays,
        };

        $written = $series->writtenInRange();
        $par = $this->parAt($target, $parDays, $totalDays);
        $remaining = max(0, $target - $written);
        $daysLeft = $state === ChallengeState::Running ? $totalDays - $elapsedDays + 1 : 0;

        return new ChallengeStanding(
            window: $window,
            state: $state,
            totalDays: $totalDays,
            elapsedDays: $elapsedDays,
            parDays: $parDays,
            written: $written,
            target: $target,
            par: $par,
            delta: $written - $par,
            remaining: $remaining,
            daysLeft: $daysLeft,
            perDayNeeded: $daysLeft > 0 ? (int) ceil($remaining / $daysLeft) : null,
            met: $state === ChallengeState::Finished ? $written >= $target : null,
            days: $days->values(),
            dailyTotals: $series->rebasedTotals(),
            parTotals: collect(range(1, $totalDays))
                ->map(fn (int $day) => $this->parAt($target, $day, $totalDays)),
        );
    }

    private function parAt(int $target, int $days, int $totalDays): int
    {
        return (int) round($target * $days / $totalDays);
    }

    private function monthOf(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d'), 'UTC')->startOfMonth();
    }
}
