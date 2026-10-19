<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Scene;
use App\Services\SceneResolver;
use Laravel\Mcp\Response;

/**
 * Turns the `scene` and `project` tool arguments into one scene.
 *
 * No match or several matches give a tool error. The error for several matches lists
 * their IDs, so Claude can call again with one.
 */
trait FindsOneScene
{
    protected function findOneScene(SceneResolver $resolver, string $reference, ?int $projectId): Scene|Response
    {
        $reference = trim($reference);
        $matches = $resolver->resolve($reference, $projectId === null ? null : (string) $projectId);

        if ($matches->isEmpty()) {
            return Response::error(ctype_digit($reference) ? "No scene with ID #{$reference} found." : "No scene matches \"{$reference}\".");
        }

        if ($matches->count() > 1) {
            $list = $matches->map(fn ($scene) => "- #{$scene->id} {$scene->name} ({$scene->book()->displayName()} / {$scene->chapter->name})")->join("\n");

            return Response::error("Several scenes match \"{$reference}\". Call again with an ID:\n{$list}");
        }

        return $matches->first();
    }
}
