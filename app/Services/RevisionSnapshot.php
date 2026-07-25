<?php

namespace App\Services;

use App\Enums\FieldKind;
use App\Models\Revision;
use App\Support\AutosavableFields;
use App\Support\EntitySnapshot;
use App\Support\SavePoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves "what did this entity look like *then*" — the state of every one of
 * its registered fields at one moment in its history.
 *
 * See {@see EntitySnapshot} for why a save point implies a value for fields it
 * never touched, which is the idea entity-level compare rests on.
 *
 * ## Why one small query per field
 *
 * The registry bounds this at six fields for a `Project` and three for a
 * `Scene` — so at most six tiny indexed lookups per side, which is cheaper to
 * reason about (and to run on all five supported engines) than a window
 * function or a correlated subquery that no two of them optimise alike.
 *
 * Neither query here selects `value`: resolving a moment needs ids and
 * timestamps only. {@see RevisionComparison} hydrates the values afterwards,
 * for the handful of fields it actually diffs.
 */
class RevisionSnapshot
{
    /**
     * The columns a snapshot needs. `value` is deliberately absent — see the
     * class docblock.
     *
     * @var list<string>
     */
    private const COLUMNS = ['id', 'save_id', 'field', 'created_at', 'user_id', 'label', 'origin', 'size_bytes'];

    /**
     * The entity as of one save point: for every registered field, the newest
     * revision at or before that moment.
     */
    public function asOf(Model $entity, SavePoint $point): EntitySnapshot
    {
        $fields = [];

        foreach ($this->registeredFields($entity) as $field => $kind) {
            $fields[$field] = $this->newestAtOrBefore($entity, $field, $point);
        }

        return new EntitySnapshot($fields, $point);
    }

    /**
     * The entity as it stands now: the newest revision of every field, with no
     * moment to bound it.
     *
     * > [!NOTE]
     * > "Newest revision" and "the value in the column" are the same thing only
     * > as long as every write goes through {@see RevisionRecorder} — the same
     * > assumption the base-hash conflict check on revert already rests on. Where
     * > the two could have drifted, trust the column and treat this as history's
     * > account of it.
     */
    public function current(Model $entity): EntitySnapshot
    {
        $fields = [];

        foreach ($this->registeredFields($entity) as $field => $kind) {
            $fields[$field] = $this->newestQuery($entity, $field)->first();
        }

        return new EntitySnapshot($fields, null);
    }

    /**
     * The newest revision of one field at or before a save point.
     *
     * The bound is on `(created_at, id)`, not on `created_at` alone: an autosave
     * burst and the Save that follows it land in the same second, and a
     * timestamp-only bound would pick between them at the database's whim.
     * Written as `created_at < :t OR (created_at = :t AND id <= :lastId)`
     * because no supported engine agrees on row-value comparison syntax.
     */
    private function newestAtOrBefore(Model $entity, string $field, SavePoint $point): ?Revision
    {
        return $this->newestQuery($entity, $field)
            ->where(fn (Builder $query) => $query
                ->where('created_at', '<', $point->savedAt)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('created_at', $point->savedAt)
                    ->where('id', '<=', $point->lastRevisionId)))
            ->first();
    }

    /**
     * One field's revisions, newest first, ready to be bounded and taken from.
     *
     * @return Builder<Revision>
     */
    private function newestQuery(Model $entity, string $field): Builder
    {
        return $entity->revisions()
            ->getQuery()
            ->reorder()
            ->where('field', $field)
            ->select(self::COLUMNS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);
    }

    /**
     * @return array<string, FieldKind>
     */
    private function registeredFields(Model $entity): array
    {
        return AutosavableFields::REGISTRY[AutosavableFields::slugFor($entity::class)][1];
    }
}
