<?php

namespace App\Support;

use App\Models\Revision;
use App\Services\RevisionSnapshot;

/**
 * What every one of an entity's registered fields held at one moment.
 *
 * The concept this whole feature turns on: **a save point is a moment, not a
 * set of values.** A save that touched only `notes` still implies a state for
 * `description` and `contents` — whatever they happened to hold then. Comparing
 * two snapshots therefore answers "everything about this scene that differs
 * between these two moments", including fields neither save wrote directly but
 * that changed in between.
 *
 * > [!NOTE]
 * > That is why a field *neither* save touched can show up as changed. It is
 * > correct, not a bug: the writer is comparing two states of the scene, not two
 * > lists of edits.
 *
 * @see RevisionSnapshot for how a moment is resolved into one of these.
 */
final readonly class EntitySnapshot
{
    /**
     * @param  array<string, Revision|null>  $fields  Every registered field of the entity,
     *                                                in registry order. Null where the field
     *                                                had no revision yet at that moment —
     *                                                it did not exist, as far as history knows.
     * @param  SavePoint|null  $point  The moment this was resolved from, or null for the
     *                                 entity's live state.
     */
    public function __construct(
        public array $fields,
        public ?SavePoint $point,
    ) {}

    /**
     * The revision this field resolved to, or null if it had none yet.
     */
    public function revisionFor(string $field): ?Revision
    {
        return $this->fields[$field] ?? null;
    }

    /**
     * The id this field resolved to — the thing two snapshots are compared by.
     * Two snapshots agreeing on a field's id means the field did not change
     * between them, and so never needs diffing.
     */
    public function revisionIdFor(string $field): ?int
    {
        return $this->revisionFor($field)?->id;
    }
}
