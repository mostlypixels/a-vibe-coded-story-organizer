<?php

namespace App\Models\Concerns;

use App\Models\Notable;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Applied to every model a note can link to (see App\Enums\NoteLinkType).
 */
trait HasNotes
{
    /**
     * The link rows of a deleted entity would point at nothing. `deleted`, not
     * `deleting`: a failed delete keeps its links.
     *
     * A database cascade skips this hook. Book, Act and Chapter delete the
     * links of their cascaded children themselves.
     */
    public static function bootHasNotes(): void
    {
        static::deleted(function (self $entity): void {
            Notable::query()
                ->where('notable_type', $entity->getMorphClass())
                ->where('notable_id', $entity->getKey())
                ->delete();
        });
    }

    /** @return MorphToMany<Note, $this> */
    public function notes(): MorphToMany
    {
        return $this->morphToMany(Note::class, 'notable');
    }
}
