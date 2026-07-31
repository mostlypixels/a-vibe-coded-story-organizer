<?php

namespace Database\Seeders\Concerns;

use App\Models\Project;
use App\Services\SceneReferenceMatcher;

/**
 * The seeded `scene_codex_entry` cache, built once the story tree and the codex
 * both exist.
 *
 * Outside seeding, that pivot is written by the Scene and CodexEntry call sites
 * that invoke `SceneReferenceMatcher` on save. `DatabaseSeeder` uses
 * `WithoutModelEvents`, and the seeders bypass those controller paths entirely,
 * so nothing recomputed it here: every seeded scene landed with an empty pivot
 * and every Codex sheet claimed no scenes mention it. The demo's most visible
 * feature looked broken in the demo — same class of gap as
 * `BackfillsSceneWordCounts`, and fixed the same way.
 *
 * Call it LAST. `syncProject()` matches each scene's contents against the
 * project's entry names and aliases, so scenes written after the sync, or
 * entries created after it, are simply not in the answer.
 *
 * The service, not a hand-rolled loop: matching is case-sensitive, whole-word
 * and Unicode-aware, and a second implementation of those rules would drift
 * from the one the app enforces — the seeded data would then demo matching the
 * app does not do.
 */
trait SyncsCodexReferences
{
    private function syncCodexReferences(Project $project): void
    {
        app(SceneReferenceMatcher::class)->syncProject($project);
    }
}
