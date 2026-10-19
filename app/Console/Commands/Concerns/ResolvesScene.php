<?php

namespace App\Console\Commands\Concerns;

use App\Models\Scene;
use App\Services\SceneResolver;

/**
 * Finds one scene from a console argument: an ID, or a name.
 * Several matches print a list, so the caller can run the command again with an ID.
 */
trait ResolvesScene
{
    protected function resolveScene(string $reference, ?string $projectId = null): ?Scene
    {
        $matches = app(SceneResolver::class)->resolve($reference, $projectId);

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->isEmpty()) {
            $this->error(ctype_digit($reference) ? "No scene with ID {$reference}." : "No scene matches \"{$reference}\".");

            return null;
        }

        $this->error("Several scenes match \"{$reference}\". Run again with an ID:");

        foreach ($matches as $scene) {
            $this->line("  {$scene->id}  {$scene->name}  ({$scene->book()->displayName()} / {$scene->chapter->name})");
        }

        return null;
    }
}
