<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Project;
use App\Rules\WithinEventWindow;

/**
 * The `min` and `max` a datetime-local field offers for an event's moment.
 *
 * This is the browser-side mirror of {@see WithinEventWindow}. The
 * server rule stays authoritative — these attributes only stop the picker from
 * offering a moment the save then rejects.
 *
 * > [!WARNING]
 * > A bookend is not bounded by the other bookend. Start is capped by the
 * > earliest regular event, and End is floored by the latest one, so moving a
 * > bookend can never jump over the story it holds. Only when the project has
 * > nothing but its two bookends does the opposite bookend become the bound.
 */
class EventWindow
{
    /** The datetime-local format. A picker reads no other shape. */
    private const FORMAT = 'Y-m-d\TH:i';

    /**
     * Bounds for a regular event, new or saved. Always inside [Start, End].
     *
     * The inline "new event" fields on the scene and codex forms always make a
     * regular event, so they use this.
     *
     * @return array{0: ?string, 1: ?string} [min, max]
     */
    public static function forRegularEvent(Project $project): array
    {
        return [
            self::format($project->startEvent()),
            self::format($project->endEvent()),
        ];
    }

    /**
     * Bounds for one event, which may be a fixed bookend.
     *
     * @param  Event|null  $event  Null for a new event, which is always regular.
     * @return array{0: ?string, 1: ?string} [min, max], either side null when open
     */
    public static function forEvent(Project $project, ?Event $event): array
    {
        if (! $event?->is_fixed) {
            return self::forRegularEvent($project);
        }

        $start = $project->startEvent();
        $end = $project->endEvent();

        if ($event->is($start)) {
            return [null, self::format($project->earliestRegularEvent() ?? $end)];
        }

        if ($event->is($end)) {
            return [self::format($project->latestRegularEvent() ?? $start), null];
        }

        return self::forRegularEvent($project);
    }

    private static function format(?Event $event): ?string
    {
        return $event?->event_datetime->format(self::FORMAT);
    }
}
