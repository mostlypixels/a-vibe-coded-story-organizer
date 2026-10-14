<?php

namespace App\Console\Commands\Concerns;

use App\Models\Project;
use App\Models\Scene;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds one scene from a console argument: an ID, or a name.
 * An exact name match wins over a partial match. Several matches print a list,
 * so the caller can run the command again with an ID.
 */
trait ResolvesScene
{
    protected function resolveScene(string $reference, ?string $projectId = null): ?Scene
    {
        if (ctype_digit($reference)) {
            $scene = $this->sceneQuery($projectId)->find((int) $reference);

            if ($scene === null) {
                $this->error("No scene with ID {$reference}.");
            }

            return $scene;
        }

        $matches = $this->sceneQuery($projectId)->whereRaw('lower(name) = ?', [mb_strtolower($reference)])->get();

        if ($matches->isEmpty()) {
            $matches = LikeSearch::whereContains($this->sceneQuery($projectId), 'name', $reference)->limit(20)->get();
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            $this->error("No scene matches \"{$reference}\".");

            return null;
        }

        $this->error("Several scenes match \"{$reference}\". Run again with an ID:");

        foreach ($matches as $scene) {
            $this->line("  {$scene->id}  {$scene->name}  ({$scene->book()->displayName()} / {$scene->chapter->name})");
        }

        return null;
    }

    /** @return Builder<Scene> */
    private function sceneQuery(?string $projectId): Builder
    {
        return $projectId === null
            ? Scene::query()
            : Project::query()->findOrFail($projectId)->sceneQuery();
    }
}
