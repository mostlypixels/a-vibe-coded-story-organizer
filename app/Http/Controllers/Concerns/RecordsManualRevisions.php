<?php

namespace App\Http\Controllers\Concerns;

use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
use Illuminate\Database\Eloquent\Model;

/**
 * The controller half of the manual-save revision checkpoint.
 *
 * Every entity controller's update() covers one or more autosaved fields, and
 * after persisting the form it must record a labeled `origin: manual` revision
 * for each field the writer actually changed. That is a two-step dance —
 * snapshot the pre-edit values, then (post-save) diff and record — and it was
 * repeated verbatim across all 7 entity controllers (Project, Act, Chapter,
 * Plotline, Event, Scene, CodexEntry). This trait owns the dance, so each
 * update() reads:
 *
 *     $before = $this->snapshotAutosaved($model, $data);
 *     // ...apply the update (varies per controller)...
 *     $this->recordManualSave($model, $before);
 *
 * The `$model->update($data)` step itself is deliberately NOT absorbed: cover
 * images, DB::transaction wrapping, non-fillable reparenting (act_id/chapter_id/
 * event_id), and reference-matcher syncing genuinely differ per controller, so
 * only the *revision* concern is shared — folding the save in too would be
 * worse than the duplication it removed.
 */
trait RecordsManualRevisions
{
    /**
     * Capture the current value of every registered autosaved field on `$model`
     * that `$data` is about to overwrite. Must be called *before* the caller
     * applies `$data`, since that's the only point the pre-edit value is still
     * readable. Pair with {@see self::recordManualSave()} after the save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function snapshotAutosaved(Model $model, array $data): array
    {
        return AutosavableFields::snapshotFieldsBeforeUpdate($model, $data);
    }

    /**
     * Record a labeled manual checkpoint for each field in `$before` whose
     * value actually changed — called *after* the caller has saved `$model`.
     *
     * The recorder and the "Saved <date>" label are resolved here, so no
     * controller needs to inject RevisionRecorder into its update() signature
     * or name the label itself (the label is {@see RevisionRecorder::
     * manualSaveLabel()}, applied by default inside recordManualChanges()).
     *
     * @param  array<string, string>  $before  {@see self::snapshotAutosaved()}'s output
     */
    protected function recordManualSave(Model $model, array $before): void
    {
        app(RevisionRecorder::class)->recordManualChanges($model, $before, request()->user());
    }
}
