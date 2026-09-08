<?php

namespace App\Services\Concerns;

use App\Models\Event;
use App\Models\Project;

/**
 * Backs every "or create a new event inline" form field (a scene's "happens
 * during" picker, the codex entry's inception/termination pickers, and the
 * quick-create endpoint behind their Save event button).
 *
 * A caller offers a select of existing events plus a title/datetime pair for a
 * new one. This trait owns only the new one: it creates the event when a title
 * is given and attaches it to the project's main plotline so it appears on the
 * timeline. A caller that wants an id falls back to its own selected id, which
 * it already holds — reading that row back would be a query for nothing.
 */
trait CreatesInlineEvents
{
    protected function createInlineEvent(Project $project, ?string $title, ?string $datetime): ?Event
    {
        if (empty($title)) {
            return null;
        }

        $event = $project->events()->create([
            'title' => $title,
            'event_datetime' => $datetime,
        ]);

        $event->plotlines()->attach($project->plotlines()->where('is_main', true)->value('id'));

        return $event;
    }
}
