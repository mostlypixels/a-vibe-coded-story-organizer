<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WordCountSnapshot;
use App\Support\WriterDay;

/**
 * Writes the project's cumulative word count onto today's snapshot row.
 *
 * The stored figure is always `SUM(scenes.word_count)` for the project at the
 * moment of the write, never a delta. Daily deltas are arithmetic on the rows,
 * done at read time.
 *
 * Model events call this, not controllers: `RevisionReverter` saves through
 * `$entity->save()` and never reaches a controller, so a controller-level call
 * would stop recording the moment somebody uses Undo. See
 * `documentation/features/writing-progress.md`.
 *
 * > [!WARNING]
 * > The day comes from the project owner's timezone, never `auth()->user()`.
 * > An import or an artisan command runs as somebody else, or as nobody.
 */
class WordCountSnapshotRecorder
{
    public function record(Project $project): void
    {
        $now = now();

        // `upsert`, not `updateOrCreate`: one atomic statement. Two autosaves in
        // the same millisecond would race updateOrCreate into a violation of the
        // (project_id, recorded_on) unique key. `upsert` skips the timestamps
        // Eloquent usually fills, so this sets them.
        WordCountSnapshot::upsert(
            [[
                'project_id' => $project->id,
                'recorded_on' => WriterDay::dateFor($project->user),
                'word_count' => (int) $project->sceneQuery()->sum('word_count'),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['project_id', 'recorded_on'],
            ['word_count', 'updated_at'],
        );
    }
}
