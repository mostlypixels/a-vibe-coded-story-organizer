<?php

namespace Database\Seeders\Concerns;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use App\Support\WordCountHistoryGenerator;
use App\Support\WriterDay;

/**
 * Gives a project a plausible writing history, so its Progress chart has
 * something to draw before anyone has written a word in it.
 *
 * `DatabaseSeeder` runs `WithoutModelEvents` (see
 * `Database\Seeders\Concerns\BackfillsSceneWordCounts`), so nothing recorded
 * a snapshot on the way in. `WordCountHistoryGenerator::plan()` invents one
 * instead, scaled to land exactly on the project's real total — see
 * `documentation/features/writing-progress.md`.
 */
trait SeedsWordCountHistory
{
    private function seedWordCountHistory(Project $project, int $days = 60, ?int $seed = null): void
    {
        $total = (int) $project->sceneQuery()->sum('word_count');
        $plan = WordCountHistoryGenerator::plan($total, $days, $seed ?? crc32($project->name));

        $today = WriterDay::for($project->user);
        $now = now();

        $rows = array_map(static fn (array $entry): array => [
            'project_id' => $project->id,
            'recorded_on' => $today->subDays($days - 1 - $entry['offset'])->toDateString(),
            'word_count' => $entry['word_count'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $plan);

        WordCountSnapshot::upsert($rows, ['project_id', 'recorded_on'], ['word_count', 'updated_at']);
    }
}
