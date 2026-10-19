<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Scene;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds scenes from a reference: an ID, or a name.
 * An exact name match wins over a partial match. The caller decides what to do with
 * no match or several matches, because the console and the MCP tools report them differently.
 */
class SceneResolver
{
    /**
     * @return Collection<int, Scene> Zero, one or several scenes. An ID gives at most one.
     */
    public function resolve(string $reference, ?string $projectId = null): Collection
    {
        if (ctype_digit($reference)) {
            return Collection::make([$this->query($projectId)->find((int) $reference)])->filter()->values();
        }

        $matches = $this->query($projectId)->whereRaw('lower(name) = ?', [mb_strtolower($reference)])->get();

        if ($matches->isEmpty()) {
            $matches = LikeSearch::whereContains($this->query($projectId), 'name', $reference)->limit(20)->get();
        }

        return $matches;
    }

    /** @return Builder<Scene> */
    private function query(?string $projectId): Builder
    {
        return $projectId === null
            ? Scene::query()
            : Project::query()->findOrFail($projectId)->sceneQuery();
    }
}
