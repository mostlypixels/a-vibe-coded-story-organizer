<?php

use App\Enums\RevisionOrigin;
use App\Models\Revision;
use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds a `baseline` revision (App\Enums\RevisionOrigin::Baseline) for every
 * existing row of every model registered in App\Support\AutosavableFields, for
 * every registered field that already holds a non-empty value.
 *
 * This is a **data** migration, not a schema change — it exists to make the
 * feature safe on day one for installs that already have content: without it,
 * the *first* autosave to an existing field would be the only revision in
 * existence, with no "value before revisions existed" row to compare against
 * or revert to.
 *
 * Deliberately routes through App\Services\RevisionRecorder::ensureBaseline(),
 * the exact same method the live write path calls before its first write to a
 * field (handoff.md §9.2's "identical code path" requirement) — this migration
 * adds no baseline-seeding logic of its own, it only drives the existing
 * check-then-seed method over every existing row. That method is already
 * idempotent (no-op if a revision for the (entity, field) pair exists) and
 * already skips null/empty values, so batching this migration and re-running
 * it after a partial failure is safe by construction.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $recorder = app(RevisionRecorder::class);

        foreach (AutosavableFields::REGISTRY as [$modelClass, $fields]) {
            // chunkById rather than all()/each(): this walks every row of
            // every registered model (Project, Act, Chapter, Plotline, Event,
            // Scene, CodexEntry) across an entire existing install, so a
            // naive eager load risks memory exhaustion on a large project
            // set.
            $modelClass::query()->chunkById(200, function ($entities) use ($recorder, $fields) {
                foreach ($entities as $entity) {
                    foreach (array_keys($fields) as $field) {
                        $recorder->ensureBaseline($entity, $field);
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deletes only the `baseline` revisions this migration (or the equivalent
     * live-path seeding) could have created, rather than a no-op: a rollback
     * that leaves baseline rows behind would make re-running `up()` diverge
     * from a fresh install's history (the idempotent no-op check would then
     * skip rows a fresh install would still seed). Safe to run even if some
     * baselines were seeded lazily by the live write path instead of this
     * migration — both routes produce identical rows, and re-seeding them on
     * the next write/backfill is itself idempotent.
     */
    public function down(): void
    {
        Revision::query()->where('origin', RevisionOrigin::Baseline)->delete();
    }
};
