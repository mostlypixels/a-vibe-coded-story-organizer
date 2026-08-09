<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A project's word history over a date range: one {@see DailyWordCount} per
 * calendar day, in order, gaps included.
 *
 * The series carries its own aggregates so a Blade view never loops to add
 * anything up. {@see App\Services\WordCountHistory} builds it.
 */
class WordCountSeries
{
    /**
     * @param  Collection<int, DailyWordCount>  $days
     */
    public function __construct(public readonly Collection $days) {}

    /**
     * Words written across the whole range, cuts subtracted.
     */
    public function writtenInRange(): int
    {
        return (int) $this->days->sum(fn (DailyWordCount $day) => $day->written);
    }

    /**
     * Words written on one day. A day outside the range, or a day with no
     * snapshot row, wrote 0.
     */
    public function writtenOn(CarbonImmutable $date): int
    {
        $day = $this->days->first(
            fn (DailyWordCount $day) => $day->date->isSameDay($date),
        );

        return $day?->written ?? 0;
    }

    /**
     * The cumulative total on the last day of the range. An empty series has
     * no days at all, so its total is 0.
     */
    public function currentTotal(): int
    {
        return $this->days->last()?->total ?? 0;
    }

    public function isEmpty(): bool
    {
        return $this->days->isEmpty();
    }
}
