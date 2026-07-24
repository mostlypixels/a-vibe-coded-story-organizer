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
