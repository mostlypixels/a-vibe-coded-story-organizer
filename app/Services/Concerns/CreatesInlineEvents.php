<?php

namespace App\Services\Concerns;

use App\Models\Project;

/**
 * Backs every "or create a new event inline" form field (a scene's "happens
 * during" picker, and the codex entry's inception/termination pickers).
 *
 * A caller offers a select of existing events plus a title/datetime pair for
 * a new one. This trait creates that event when a title is given, attaches it
 * to the project's main plotline so it appears on the timeline, and returns
 * its id; otherwise it returns the id already selected (which may be null).
 */
trait CreatesInlineEvents
{
    protected function resolveInlineEvent(Project $project, ?string $title, ?string $datetime, ?int $existingId): ?int
    {
        if (! empty($title)) {
            $event = $project->events()->create([
                'title' => $title,
                'event_datetime' => $datetime,
            ]);

            $event->plotlines()->attach($project->plotlines()->where('is_main', true)->value('id'));

            return $event->id;
        }

        return $existingId;
    }
}
