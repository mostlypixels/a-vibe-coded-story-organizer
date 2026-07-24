<?php

namespace App\Services;

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Models\User;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place the application writes to the `revisions` table.
 *
 * Called by App\Http\Controllers\FieldAutosaveController (task 6) and by the
 * baseline backfill migration (task 5) — the "identical code path" handoff.md
 * §9.2 requires, so the live write path and the backfill can never drift.
 *
 * Deliberately does *not* decide whether to write at all: the byte-identical
 * no-op check (§2.2 — "typing something and undoing it leaves no trace") is
 * the caller's job, comparing the incoming value against the entity's current
 * column value before ever calling record(). This class only knows how to
 * coalesce and how to seed a baseline.
 */
class RevisionRecorder
{
    /**
     * Record a new value for one revisionable field, coalescing with an
     * already-open automatic revision when one exists.
     *
     * `origin: automatic` revisions coalesce: if an automatic revision for
     * this exact (entity, field) was created within the field's configured
     * window (App\Support\AutosavableFields::windowSeconds()), that row's
     * `value`/`size_bytes` are overwritten in place rather than inserting a
     * new row. Every other origin (manual, revert, import) always inserts a
     * fresh row — this is what makes a form-submit Save a permanent,
     * individually visible entry even seconds after an autosave.
     */
    public function record(
        Model $entity,
        string $field,
        string $value,
        User $user,
        RevisionOrigin $origin,
        ?string $label = null,
    ): Revision {
        $this->ensureBaseline($entity, $field);

        $open = $origin === RevisionOrigin::Automatic
            ? $this->openAutomaticRevision($entity, $field)
            : null;

        if ($open !== null) {
            $open->update(['value' => $value, 'size_bytes' => strlen($value)]);

            return $open;
        }

        return $entity->revisions()->create([
            'field' => $field,
            'value' => $value,
            'size_bytes' => strlen($value),
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $user->id,
            'label' => $label,
            'origin' => $origin,
            'created_at' => now(),
        ]);
    }

    /**
     * The auto-generated label every full-form Save button's manual checkpoint
     * gets, e.g. "Saved 24 July 10:43" — the same `d F H:i` format
     * RevisionController's revert action already uses for its own
     * auto-generated "Reverted to :date" label, kept as one shared format
     * rather than each caller picking its own.
     */
    public static function manualSaveLabel(): string
    {
        return __('Saved :date', ['date' => now()->format('d F H:i')]);
    }

    /**
     * Record a manual checkpoint for every field in `$before` whose value
     * changed compared to `$entity`'s current (already-saved) value.
     *
     * `$before` is App\Support\AutosavableFields::snapshotFieldsBeforeUpdate()'s
     * output, taken by the caller *before* it applied the form's data to
     * `$entity` — this method has no other way to know the pre-edit value once
     * that update has already overwritten it. A full-form Save button commonly
     * covers several autosaved fields in one submit (e.g. Project's
     * description/rights/dedication/acknowledgements/preface/postface); only
     * the fields a writer actually touched get a new row, so clicking Save
     * after editing just one of them doesn't also spam empty-diff manual rows
     * for the rest.
     *
     * `$label` defaults to {@see self::manualSaveLabel()} — the only label
     * every caller ever passes, so it lives here rather than being repeated at
     * each call site. (It cannot be a parameter default: PHP default values
     * must be constant expressions, and the label embeds `now()`.)
     */
    public function recordManualChanges(Model $entity, array $before, User $user, ?string $label = null): void
    {
        $label ??= self::manualSaveLabel();

        foreach ($before as $field => $previousValue) {
            $currentValue = (string) ($entity->getAttribute($field) ?? '');

            if ($currentValue === $previousValue) {
                continue;
            }

            $this->record($entity, $field, $currentValue, $user, RevisionOrigin::Manual, $label);
        }
    }

    /**
     * Seed a `baseline` revision holding the entity's *current* (pre-edit)
     * value for this field, but only if no revision at all exists yet for
     * this (entity, field) pair — a no-op on every later call.
     *
     * `created_at` is stamped `$entity->updated_at`, not `now()`: that value
     * provably held from that timestamp onward, whereas stamping `now()`
     * would misrepresent the entire pre-baseline era for compare-by-date
     * (handoff.md §9.2). `user_id` is the project owner, not any particular
     * editor, since no one "wrote" the baseline.
     *
     * Skipped entirely when the field's current value is null/empty — an
     * empty field has nothing worth preserving.
     */
    public function ensureBaseline(Model $entity, string $field): void
    {
        if ($entity->revisions()->where('field', $field)->exists()) {
            return;
        }

        $current = $entity->getAttribute($field);

        if ($current === null || $current === '') {
            return;
        }

        $entity->revisions()->create([
            'field' => $field,
            'value' => $current,
            'size_bytes' => strlen($current),
            'project_id' => $entity->revisionProject()->id,
            'user_id' => $entity->revisionProject()->user_id,
            'label' => null,
            'origin' => RevisionOrigin::Baseline,
            'created_at' => $entity->updated_at,
        ]);
    }

    /**
     * The most recently recorded revision for this (entity, field) pair, if
     * any — used by callers to decide whether a byte-identical write can be
     * skipped, and to report the revision id a save just touched.
     */
    public function lastRevisionFor(Model $entity, string $field): ?Revision
    {
        return $entity->revisions()->where('field', $field)->latest('created_at')->first();
    }

    /**
     * The `value` of {@see self::lastRevisionFor()}, or null if this
     * (entity, field) pair has no revision yet.
     */
    public function lastValueFor(Model $entity, string $field): ?string
    {
        return $this->lastRevisionFor($entity, $field)?->value;
    }

    /**
     * The still-open automatic revision for this (entity, field) pair, if
     * its coalescing window (App\Support\AutosavableFields::windowSeconds())
     * hasn't closed yet.
     */
    private function openAutomaticRevision(Model $entity, string $field): ?Revision
    {
        $slug = AutosavableFields::slugFor($entity::class);
        $window = AutosavableFields::windowSeconds($slug, $field);

        return $entity->revisions()
            ->where('field', $field)
            ->where('origin', RevisionOrigin::Automatic)
            ->where('created_at', '>=', now()->subSeconds($window))
            ->latest('created_at')
            ->first();
    }
}
