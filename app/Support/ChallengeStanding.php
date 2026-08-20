<?php

namespace App\Support;

use App\Enums\ChallengeState;
use Illuminate\Support\Collection;

/**
 * One challenge, scored against one day. Everything a card or a table row
 * shows, already calculated, so Blade only reads.
 *
 * Nothing here is stored: {@see App\Services\ChallengeProgress} rebuilds a
 * standing from the snapshot rows on each request.
 *
 * `par` counts **finished** days only, so day 1 opens at par 0 and full par
 * arrives only once the window is over — the same forgiveness the daily
 * streak gives. `parTotals` is the chart line and keeps the end-of-day
 * figure for every day, so the card reads yesterday's point.
 */
final readonly class ChallengeStanding
{
    /**
     * @param  Collection<int, DailyWordCount>  $days  one entry per day of the window
     * @param  Collection<int, int>  $dailyTotals  words so far, rebased to 0 at the window's first day
     * @param  Collection<int, int>  $parTotals  the par line, end-of-day figure per day
     */
    public function __construct(
        public ChallengeWindow $window,
        public ChallengeState $state,
        public int $totalDays,
        public int $elapsedDays,
        public int $parDays,
        public int $written,
        public int $target,
        public int $par,
        public int $delta,
        public int $remaining,
        public int $daysLeft,
        public ?int $perDayNeeded,
        public ?bool $met,
        public Collection $days,
        public Collection $dailyTotals,
        public Collection $parTotals,
    ) {}
}
