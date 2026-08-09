<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use App\Support\DailyWordCount;
use App\Support\WordCountSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The read path: snapshot rows become a per-day series with derived deltas.
 *
 * Two queries, whatever the range length — the rows in the range, and the one
 * row before it. The arithmetic runs in PHP. The controller resolves and
 * authorizes; this service owns the queries (see {@see ProjectSearch}).
 *
 * Three rules, all binding:
 *   - A day with no row inherits the previous day's total and wrote 0. No row
 *     means no save, which means no change — not missing data.
 *   - The delta of the first day needs the row *before* the range. Without it,
 *     every range opens with a false spike that counts all earlier writing.
 *   - With no earlier row, the previous total is 0. A project genuinely had no
 *     words before its first row, so its first writing day counts in full.
 *     This is why the feature stores no baseline row anywhere.
 */
class WordCountHistory
{
    public function series(Project $project, CarbonImmutable $from, CarbonImmutable $to): WordCountSeries
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        if ($to->lessThan($from)) {
            return new WordCountSeries(collect());
        }

        $totals = $this->totalsInRange($project, $from, $to);
        $runningTotal = $this->totalBefore($project, $from);

        $days = collect();

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $previousTotal = $runningTotal;
            $runningTotal = $totals[$date->toDateString()] ?? $runningTotal;

            $days->push(new DailyWordCount(
                date: $date,
                total: $runningTotal,
                written: $runningTotal - $previousTotal,
            ));
        }

        return new WordCountSeries($days);
    }

    /**
     * How many days in a row the project met its daily goal, ending today.
     *
     * One query, and no cap: a day the writer wrote nothing has no row, so an
     * N-day streak is exactly N consecutive rows. The walk stops at the first
     * day below the goal, and the query builder keeps 1,500 rows as 1,500
     * integers instead of 1,500 models.
     *
     * Today extends a streak but never breaks one — a writer at breakfast has
     * not failed the day yet.
     */
    public function currentStreak(Project $project, int $dailyGoal, CarbonImmutable $today): int
    {
        if ($dailyGoal < 1) {
            return 0;
        }

        /** @var Collection<string, int> $totals newest first, `Y-m-d => cumulative total` */
        $totals = DB::table('word_count_snapshots')
            ->where('project_id', $project->id)
            ->where('recorded_on', '<=', $today->toDateString())
            ->orderByDesc('recorded_on')
            ->pluck('word_count', 'recorded_on');

        $dates = $totals->keys()->all();
        $values = $totals->values()->all();

        // The day before a row is the next row down this list, because the
        // days between them hold no row. Before the first row ever, the
        // project held 0 words.
        $written = fn (int $index): int => (int) $values[$index] - (int) ($values[$index + 1] ?? 0);

        $day = $today;
        $index = 0;
        $streak = 0;

        $todayQualifies = isset($dates[0])
            && $dates[0] === $today->toDateString()
            && $written(0) >= $dailyGoal;

        if (! $todayQualifies) {
            $index = ($dates[0] ?? null) === $today->toDateString() ? 1 : 0;
            $day = $today->subDay();
        }

        while (($dates[$index] ?? null) === $day->toDateString() && $written($index) >= $dailyGoal) {
            $streak++;
            $index++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * The range's rows as `Y-m-d => cumulative total`.
     *
     * @return Collection<string, int>
     */
    private function totalsInRange(Project $project, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return WordCountSnapshot::query()
            ->where('project_id', $project->id)
            ->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'word_count'])
            // Keyed by 'Y-m-d': `recorded_on` casts to a date object, which
            // cannot be an array key.
            ->mapWithKeys(fn (WordCountSnapshot $row) => [
                $row->recorded_on->toDateString() => (int) $row->word_count,
            ]);
    }

    /**
     * The cumulative total the project carried into the range: the last row
     * before it, or 0 when the range starts before the project's first row.
     */
    private function totalBefore(Project $project, CarbonImmutable $from): int
    {
        return (int) WordCountSnapshot::query()
            ->where('project_id', $project->id)
            ->where('recorded_on', '<', $from->toDateString())
            ->latest('recorded_on')
            ->value('word_count');
    }
}
