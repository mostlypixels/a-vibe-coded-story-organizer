<?php

namespace App\Support;

use App\Models\Event;

/**
 * The confirm text shown before an event is deleted. The database cascade deletes
 * the codex attribute values that start at the event, and clears the event of the
 * scenes that happen during it. The writer must see both before the delete.
 *
 * > [!WARNING]
 * > Load the counts with one query for the whole list (`withCount()` on the query), never
 * > per row (`loadCount()` inside a loop).
 */
class EventDeleteWarning
{
    /** @return array<int, string> */
    public static function countRelations(): array
    {
        return ['attributeValues', 'scenes'];
    }

    public static function for(Event $event): string
    {
        $sentences = [__('Are you sure you want to delete this event?')];

        if ($event->attribute_values_count > 0) {
            $sentences[] = trans_choice(
                '{1} It also deletes :count codex attribute value.|[2,*] It also deletes :count codex attribute values.',
                $event->attribute_values_count,
            );
        }

        if ($event->scenes_count > 0) {
            $sentences[] = trans_choice(
                '{1} :count scene loses its event.|[2,*] :count scenes lose their event.',
                $event->scenes_count,
            );
        }

        return implode(' ', $sentences);
    }
}
