<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Exceptions\RevisionConflictException;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;
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

        return $this->restore($entity, $revision, $user);
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
     * Write `$revision`'s value back onto the live column and record the revert.
     *
     * Assumes the base hash has already been checked ({@see self::assertUnchanged()}).
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
    private function restore(Model $entity, Revision $revision, User $user): string
    {
        $field = $revision->field;
        $slug = AutosavableFields::slugFor($entity::class);

        Validator::make(
            ['value' => $revision->value],
            ['value' => AutosavableFields::validationRule($slug, $field)],
        )->validate();

        $entity->{$field} = $revision->value;
        $entity->save(); // Mutators run here, e.g. SanitizesRichHtml for rich fields.

        $storedValue = (string) ($entity->fresh()->getAttribute($field) ?? '');

        $this->recorder->record(
            $entity,
            $field,
            $storedValue,
            $user,
            RevisionOrigin::Revert,
            __('Reverted to :date', ['date' => $revision->created_at->format('d F H:i')]),
        );

        return $field;
    }
}
