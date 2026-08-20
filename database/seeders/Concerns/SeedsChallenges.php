<?php

namespace Database\Seeders\Concerns;

use App\Enums\ChallengeRecurrence;
use App\Models\Challenge;
use App\Models\Project;
use App\Services\WordCountHistory;
use App\Support\WriterDay;

/**
 * Gives a project one finished challenge (last calendar month) and one
 * running monthly challenge (this month), both scored against the history
 * `SeedsWordCountHistory` already wrote.
 *
 * Targets come from that history, not a hard-coded figure: the finished
 * challenge's target is a share of what it actually wrote that month, so it
 * always reads *met*, and the running one's target scales the whole
 * project's average daily pace to the current month's length, so it reads a
 * believable ahead-or-behind on whatever day the seeder runs.
 *
 * > [!WARNING]
 * > Only ever seed challenges for a fictional project (Melusine) — the same
 * > rule as `SeedsWordCountHistory`.
 */
trait SeedsChallenges
{
    private function seedChallenges(Project $project, string $finishedName, string $runningName, int $days = 60): void
    {
        $today = WriterDay::for($project->user);
        $history = app(WordCountHistory::class);

        $lastMonthStart = $today->startOfMonth()->subMonthNoOverflow();
        $lastMonthEnd = $lastMonthStart->endOfMonth()->startOfDay();
        $writtenLastMonth = $history->series($project, $lastMonthStart, $lastMonthEnd)->writtenInRange();

        Challenge::create([
            'project_id' => $project->id,
            'name' => $finishedName,
            'recurrence' => ChallengeRecurrence::None,
            'starts_on' => $lastMonthStart,
            'ends_on' => $lastMonthEnd,
            'target_words' => max(1, (int) round($writtenLastMonth * 0.85)),
        ]);

        $averageDaily = $history->series($project, $today->subDays($days - 1), $today)->writtenInRange() / $days;

        Challenge::create([
            'project_id' => $project->id,
            'name' => $runningName,
            'recurrence' => ChallengeRecurrence::Monthly,
            'starts_on' => $today->startOfMonth(),
            'ends_on' => null,
            'target_words' => max(1, (int) round($averageDaily * $today->daysInMonth)),
        ]);
    }
}
