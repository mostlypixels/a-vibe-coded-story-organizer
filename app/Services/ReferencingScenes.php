<?php

namespace App\Services;

use App\Models\CodexEntry;
use App\Models\Scene;
use Illuminate\Support\Collection;

/**
 * Orders the scenes that reference a codex entry: assigned scenes by event,
 * then unassigned scenes by manuscript position.
 */
class ReferencingScenes
{
    /**
     * Scenes with an event first, ordered by event_datetime then event id;
     * then scenes with no event, ordered by act, chapter, and scene position.
     *
     * @return Collection<int, Scene>
     */
    public function forEntry(CodexEntry $codexEntry): Collection
    {
        return $codexEntry->referencingScenes()
            ->with('chapter.act', 'event')
            ->get()
            ->sortBy(fn (Scene $scene) => [
                $scene->event === null ? 1 : 0,
                $scene->event?->event_datetime?->timestamp ?? 0,
                $scene->event?->id ?? 0,
                $scene->chapter->act->position,
                $scene->chapter->position,
                $scene->position,
            ])
            ->values();
    }
}
