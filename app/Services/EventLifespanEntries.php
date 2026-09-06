<?php

namespace App\Services;

use App\Models\CodexEntry;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Codex entries this event begins or ends. {@see CodexEntry} declares
 * inceptionEvent()/terminationEvent(); nothing walks back from the event side.
 */
class EventLifespanEntries
{
    /**
     * @return array{inceptions: Collection<int, CodexEntry>, terminations: Collection<int, CodexEntry>}
     */
    public function forEvent(Event $event): array
    {
        return [
            'inceptions' => CodexEntry::query()->where('inception_event_id', $event->id)->orderBy('name')->get(),
            'terminations' => CodexEntry::query()->where('termination_event_id', $event->id)->orderBy('name')->get(),
        ];
    }
}
