<?php

namespace App\Services;

use App\Models\Scene;
use Illuminate\Support\Facades\DB;

/**
 * Duplicates a scene into a new row in the same chapter, right after the
 * original. See documentation/architecture/README.md -> Duplicating entities.
 *
 * Copies `description`, `contents`, `notes`, `status`, `chapter_id`, `event_id`,
 * and the `event_scene` "mentions" pivot. Never copies `share_token` or
 * `share_expires_at` — a copy is always unshared, and `scenes.share_token`
 * carries a unique index that a copied value would violate. `word_count` is
 * left to Scene's own `saving` hook.
 */
class SceneDuplicator
{
    public function __construct(private readonly SceneReferenceMatcher $matcher) {}

    /**
     * Insert the copy at `$scene->position + 1`, shifting later siblings down,
     * inside one transaction. `scene_codex_entry` is never copied — it is
     * rebuilt from the copy's own contents, same as any other save.
     */
    public function duplicate(Scene $scene, string $name): Scene
    {
        return DB::transaction(function () use ($scene, $name) {
            $position = $scene->makeRoomAfter();

            // Created off the chapter relation, not Scene::create() — chapter_id is
            // deliberately absent from Scene::$fillable, and the relation sets the
            // foreign key directly rather than through mass assignment.
            $copy = $scene->chapter->scenes()->create([
                'name' => $name,
                'description' => $scene->description,
                'contents' => $scene->contents,
                'notes' => $scene->notes,
                'status' => $scene->status,
                'event_id' => $scene->event_id,
                'position' => $position,
            ]);

            $copy->mentionedEvents()->sync($scene->mentionedEvents()->pluck('events.id'));

            $this->matcher->syncScene($copy);

            return $copy;
        });
    }
}
