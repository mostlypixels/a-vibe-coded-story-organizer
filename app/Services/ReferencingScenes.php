<?php

namespace App\Services;

use App\Models\CodexEntry;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Orders the scenes that reference a codex entry: assigned scenes by event,
 * then unassigned scenes by manuscript position.
 */
class ReferencingScenes
{
    /**
     * Scenes with an event first, ordered by event_datetime then event id;
     * then scenes with no event, ordered by book, act, chapter, and scene position.
     *
     * @return Collection<int, Scene>
     */
    public function forEntry(CodexEntry $codexEntry): Collection
    {
        return $codexEntry->referencingScenes()
            ->with('chapter.act.book', 'event')
            ->get()
            ->sortBy(fn (Scene $scene) => [
                $scene->event === null ? 1 : 0,
                $scene->event?->event_datetime?->timestamp ?? 0,
                $scene->event?->id ?? 0,
                $scene->chapter->act->book->position,
                $scene->chapter->act->position,
                $scene->chapter->position,
                $scene->position,
            ])
            ->values();
    }

    /**
     * Codex entries a scene references, ordered by (type, name) for a stable read.
     *
     * @return Collection<int, CodexEntry>
     */
    public function forScene(Scene $scene): Collection
    {
        return $this->queryForScene($scene)->get();
    }

    /**
     * The same query as {@see forScene()}, left unrun so a caller can paginate
     * it. The order lives in SQL, so the database can page it honestly.
     *
     * @return BelongsToMany<CodexEntry, Scene>
     */
    public function queryForScene(Scene $scene): BelongsToMany
    {
        return $scene->codexReferences()->orderBy('type')->orderBy('name');
    }
}
