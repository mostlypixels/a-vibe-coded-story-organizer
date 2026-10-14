<?php

namespace App\Console\Commands;

use App\Models\Scene;
use Illuminate\Console\Command;

/** Prints the raw Markdown of scenes, so the scene-reviewer agent can read more of the story. */
class SceneShowCommand extends Command
{
    protected $signature = 'scene:show {scenes* : Scene IDs}';

    protected $description = 'Print the contents of one or more scenes';

    public function handle(): int
    {
        $ids = array_map('intval', $this->argument('scenes'));
        $scenes = Scene::query()->with('chapter')->findMany($ids)->keyBy('id');

        foreach ($ids as $id) {
            $scene = $scenes->get($id);

            if ($scene === null) {
                $this->error("No scene with ID {$id}.");

                continue;
            }

            $this->line("=== Scene {$scene->id}: {$scene->name} (chapter: {$scene->chapter->name}) ===");
            $this->line((string) $scene->contents);
            $this->newLine();
        }

        return $scenes->isEmpty() ? self::FAILURE : self::SUCCESS;
    }
}
