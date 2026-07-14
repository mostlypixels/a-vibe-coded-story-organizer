<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\SceneReferenceMatcher;
use Illuminate\Console\Command;

/**
 * Rebuilds the derived `scene_codex_entry` pivot from scratch, project by project.
 *
 * Needed the first time this feature ships against an existing database (pre-existing
 * scenes were never scanned) and as a manual escape hatch if the pivot is ever suspected
 * to have drifted. Normal operation never needs this — SceneReferenceMatcher keeps the
 * pivot in sync on every scene/codex entry save (see documentation/architecture.md →
 * "Scene references").
 */
class SyncCodexReferencesCommand extends Command
{
    protected $signature = 'codex:sync-references {project? : Only resync this project ID, instead of every project}';

    protected $description = "Rebuild every scene's codex entry references (the scene_codex_entry pivot)";

    public function handle(SceneReferenceMatcher $matcher): int
    {
        $projects = $this->argument('project') !== null
            ? Project::query()->where('id', $this->argument('project'))->get()
            : Project::all();

        if ($projects->isEmpty()) {
            $this->error('No matching project found.');

            return self::FAILURE;
        }

        $this->withProgressBar($projects, function (Project $project) use ($matcher) {
            $matcher->syncProject($project);
        });

        $this->newLine(2);
        $this->info("Synced codex references for {$projects->count()} project(s).");

        return self::SUCCESS;
    }
}
