<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The one implementation of "put an older value back" (task 16,
 * expanded/architecture.md "App\Services\RevisionReverter").
 *
 * Extracted from RevisionController::revert() so the single-field revert and the
 * whole-save undo (task 17) cannot drift: both run the same four steps, in the
 * same order, and any rule added here applies to both by construction.
 *
 * Every revert is **additive**. Nothing in the history is edited or deleted —
 * the value being reverted away from stays exactly where it is, and the restored
 * value arrives as a *new* `origin: revert` row. That is what makes "undo a
 * revert by reverting again" work for free: a revert is just another
 * forward-moving write.
 */
class RevisionReverter
{
    public function __construct(private readonly RevisionRecorder $recorder) {}

    /**
     * Restore one field to `$revision`'s value, returning the field name.
     *
     * `$baseHash` is the hash of the value the *page the request came from* was
     * showing. Checking it is the concurrency answer: if a second tab (or a
     * still-in-flight autosave) wrote the field after that page was drawn, this
     * revert would silently clobber text the writer never chose to discard.
     *
     * @throws RevisionConflictException when the stored value has moved.
     */
    public function revertField(Model $entity, Revision $revision, string $baseHash, User $user): string
    {
        $this->assertUnchanged($entity, $revision->field, $baseHash);

        return $this->restore(
            $entity,
            $revision->field,
            (string) $revision->value,
            __('Reverted to :date', ['date' => $revision->created_at->format('d F H:i')]),
            $user,
        );
    }

    /**
     * Undo a whole save: put every field it touched back to the value it held
     * *before* it, and return the restored field names (task 17).
     *
     * **Only the fields that save touched.** Never a whole-entity rollback to
     * that moment — that would silently discard unrelated later edits to other
     * fields, which is a different and much larger promise (grill decision 9).
     *
     * **All-or-nothing.** One transaction, and every base hash is verified
     * before anything at all is written: a half-applied undo — some fields back,
     * some not — is a state the writer never asked for and cannot easily
     * recognise, which is worse than the undo simply refusing.
     *
     * **The undo is itself one save point**, symmetric with what it undoes and
     * immediately undoable in turn — which is what `startNewSave()` buys.
     *
     * @param  Collection<int, Revision>  $group  Every row sharing the save id.
     * @param  array<string, string>  $baseHashes  field => hash of the value the page showed.
     * @return list<string> The restored field names.
     *
     * @throws RevisionConflictException when any field's stored value has moved.
     */
    public function revertSave(Model $entity, Collection $group, array $baseHashes, User $user): array
    {
        // One row per field: the *earliest*, because it is that row's
        // predecessor that holds the value this field had before the save. (A
        // group normally has one row per field; taking the earliest is what
        // makes a group that coalesced twice into the same field still resolve
        // to the state before the burst.)
        $rows = $group->sortBy([['created_at', 'asc'], ['id', 'asc']])->unique('field');

        return DB::transaction(function () use ($entity, $rows, $baseHashes, $user): array {
            foreach ($rows as $row) {
                // A field with no submitted hash is treated as a conflict rather
                // than as permission to write it blind: the form is supposed to
                // carry one per field, so a missing one means the page did not
                // see what it is about to overwrite.
                $this->assertUnchanged($entity, $row->field, $baseHashes[$row->field] ?? '');
            }

            $this->recorder->startNewSave($entity);

            $label = __('Undid the save of :date', [
                'date' => $rows->first()->created_at->format('d F H:i'),
            ]);

            return $rows
                ->map(fn (Revision $row): string => $this->restore(
                    $entity,
                    $row->field,
                    // No predecessor means the save *created* this field's
                    // content, so "before it" is empty. Every registered field
                    // is `nullable`, so emptying one is a legal state.
                    (string) ($this->predecessorOf($entity, $row)?->value ?? ''),
                    $label,
                    $user,
                ))
                ->values()
                ->all();
        });
    }

    /**
     * Fail unless the field's stored value still hashes to `$baseHash`.
     *
     * Split out from {@see self::restore()} rather than inlined because the
     * whole-save undo has to check *every* field before writing *any* of them —
     * a half-applied undo is worse than none. Same hashing as
     * FieldAutosaveController and RevisionController's `baseHashes()`: the
     * server hashes what it stored, never what a client sent.
     *
     * @throws RevisionConflictException
     */
    public function assertUnchanged(Model $entity, string $field, string $baseHash): void
    {
        $currentValue = (string) ($entity->getAttribute($field) ?? '');

        if ($baseHash !== hash('sha256', $currentValue)) {
            throw RevisionConflictException::valueChangedElsewhere();
        }
    }

    /**
     * Write an old value back onto the live column and record the revert.
     *
     * Assumes the base hash has already been checked ({@see self::assertUnchanged()}).
     *
     * Takes a value and a label rather than the `Revision` they came from,
     * because the two callers reach them differently: a single-field revert
     * restores *that* revision's value, while an undo restores the value the
     * field held **before** the row the save wrote — which is a different row,
     * and sometimes no row at all.
     *
     * The old value is re-validated against **today's** rules before it is
     * assigned: `AutosavableFields::validationRule()` is the single source of
     * those rules, and they can have tightened since the revision was recorded.
     * An old value must never reach the column through a door a normal save
     * would have closed.
     *
     * The recorded value is read back *after* `save()`, so it is what the
     * database actually holds — the model's mutators (e.g. `SanitizesRichHtml`
     * on a rich field) may well have changed it on the way in, and a revision
     * that disagreed with its own column would poison every later diff.
     */
    private function restore(Model $entity, string $field, string $value, string $label, User $user): string
    {
        $slug = AutosavableFields::slugFor($entity::class);

        Validator::make(
            ['value' => $value],
            ['value' => AutosavableFields::validationRule($slug, $field)],
        )->validate();

        $entity->{$field} = $value;
        $entity->save(); // Mutators run here, e.g. SanitizesRichHtml for rich fields.

        $storedValue = (string) ($entity->fresh()->getAttribute($field) ?? '');

        $this->recorder->record($entity, $field, $storedValue, $user, RevisionOrigin::Revert, $label);

        return $field;
    }

    /**
     * The revision that held this field's value *before* `$row` — the newest one
     * strictly older than it, or null when `$row` is the field's first.
     *
     * Ordered by `(created_at, id)`, never by `created_at` alone: an autosave
     * burst and the Save that follows it land in the same second, and a
     * timestamp-only bound would pick between them at the database's whim. The
     * same `(created_at, id)` walk {@see RevisionSnapshot} and
     * {@see RevisionRecorder} both use — deliberately re-stated here rather than
     * shared, since each of the three wants a different thing out of the row it
     * finds (a Revision, a bound, a value).
     */
    private function predecessorOf(Model $entity, Revision $row): ?Revision
    {
        return $entity->revisions()
            ->where('field', $row->field)
            ->where(fn (Builder $query) => $query
                ->where('created_at', '<', $row->created_at)
                ->orWhere(fn (Builder $tie) => $tie
                    ->where('created_at', $row->created_at)
                    ->where('id', '<', $row->id)))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
