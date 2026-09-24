<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Scene;
use App\Models\User;
use App\Services\Concerns\CreatesInlineEvents;
use App\Support\AutosavableFields;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates a scene from its full form: the inline "happens during"
 * event, the mentioned events, the codex references and the manual revision
 * checkpoint.
 *
 * Each save runs in one transaction, so a failure after the inline event insert
 * leaves no orphan event. Every save resyncs the codex references of the scene,
 * because a change to the contents is the reason for most saves.
 *
 * Nothing here reads the request. The controller resolves the destination
 * chapter and authorizes, then hands over validated data.
 */
class SceneSaver
{
    use CreatesInlineEvents;

    public function __construct(
        private readonly SceneReferenceMatcher $matcher,
        private readonly RevisionRecorder $recorder,
    ) {}

    /** @param  array<string, mixed>  $validated */
    public function create(Chapter $chapter, array $validated): Scene
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $scene = $chapter->scenes()->create(
                $this->sceneAttributes($validated) + ['event_id' => $this->eventId($chapter, $validated)]
            );

            $this->syncRelations($scene, $validated);

            return $scene;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  User  $user  Credited on the manual revision checkpoint.
     */
    public function update(Scene $scene, Chapter $chapter, array $validated, User $user): void
    {
        $attributes = $this->sceneAttributes($validated);

        // A first-ever save seeds its baseline with this timestamp, and the save overwrites it.
        $heldSince = $scene->updated_at;
        $before = AutosavableFields::snapshotFieldsBeforeUpdate($scene, $attributes);

        DB::transaction(function () use ($scene, $chapter, $validated, $user, $attributes, $heldSince, $before) {
            $scene->fill($attributes + ['event_id' => $this->eventId($chapter, $validated)]);

            if ($scene->chapter_id !== $chapter->id) {
                $scene->moveToEndOf($chapter, 'chapter');
            }

            $scene->save();

            $this->syncRelations($scene, $validated);

            $this->recorder->recordManualChanges($scene, $before, $user, heldSince: $heldSince);
        });
    }

    /**
     * The new inline event wins over the picked one.
     *
     * @param  array<string, mixed>  $validated
     */
    private function eventId(Chapter $chapter, array $validated): int|string|null
    {
        return $this->createInlineEvent(
            $chapter->project(),
            $validated['new_event_title'] ?? null,
            $validated['new_event_datetime'] ?? null,
        )?->id ?? $validated['event_id'] ?? null;
    }

    /** @param  array<string, mixed>  $validated */
    private function syncRelations(Scene $scene, array $validated): void
    {
        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        // Runs against the saved contents.
        $this->matcher->syncScene($scene);
    }

    /**
     * Scene column values, without the keys that the relations and the inline event use.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sceneAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['chapter_id', 'event_id', 'new_event_title', 'new_event_datetime', 'mentioned_events'])
            ->all();
    }
}
