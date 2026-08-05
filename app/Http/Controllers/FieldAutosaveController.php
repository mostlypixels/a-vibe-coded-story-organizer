<?php

namespace App\Http\Controllers;

use App\Enums\RevisionOrigin;
use App\Events\SceneContentsChanged;
use App\Models\Scene;
use App\Services\RevisionRecorder;
use App\Services\SceneReferenceMatcher;
use App\Support\AutosavableFields;
use App\Support\WordCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single HTTP surface every autosaved field goes through — one action, not
 * one controller per model, because
 * {@see AutosavableFields::REGISTRY} is the only place a `{entity}` slug resolves
 * to a model+field. An unregistered slug never reaches this class at all: the
 * router's `->whereIn('entity', AutosavableFields::slugs())` 404s it first.
 *
 * The server is the sole hash authority (§9.13): the `hash` this returns is
 * always computed from the value actually persisted (post-mutator — e.g. a rich
 * field's `SanitizesRichHtml` set-mutator), never an echo of what the client
 * sent. Concretely, this is what stops a rich-HTML field's *second* autosave
 * from 409ing forever — if the client instead hashed what it sent, that hash
 * would never match the sanitized, already-differently-shaped stored value.
 */
class FieldAutosaveController extends Controller
{
    public function update(string $entity, int $id, string $field, Request $request, RevisionRecorder $recorder): JsonResponse
    {
        [$modelClass] = AutosavableFields::resolveField($entity, $field);

        $model = $modelClass::findOrFail($id);

        $this->authorize('update', $model->revisionProject());

        $validated = $request->validate([
            'value' => AutosavableFields::validationRule($entity, $field),
            'base_hash' => ['required', 'string'],
            'run_matcher' => ['sometimes', 'boolean'],
        ]);

        $currentValue = (string) ($model->getAttribute($field) ?? '');
        $storedHash = hash('sha256', $currentValue);

        if ($validated['base_hash'] !== $storedHash) {
            return response()->json(['message' => __('This field was changed elsewhere.')], 409);
        }

        $model->{$field} = $validated['value'] ?? '';
        $model->save(); // mutators run here, e.g. SanitizesRichHtml for rich fields.

        // Read back from memory, not with a fresh() round-trip. SanitizesRichHtml is
        // an `Attribute::make(set:)` mutator, so it ran at *assignment* above and the
        // in-memory attribute already holds exactly what was written — re-SELECTing
        // the row would return the same string at the cost of reading every column,
        // Scene.contents included. It is also the safer of the two: fresh() would
        // hand back a concurrent writer's value, and this endpoint must hash what
        // *it* stored (§9.13 — the server is the sole hash authority).
        $storedValue = (string) ($model->getAttribute($field) ?? '');

        // The authoritative word count, read after save (word-count spec, task 5) —
        // never computed from what the client sent, the same rule the hash above
        // follows. Scene.contents is the one field with a stored column: Scene's
        // `saving` hook (task 4) has already recounted it as part of this save, so
        // reading $model->word_count reuses that number instead of recounting it a
        // second time. Every other field (including Scene.description/notes, which
        // share the model but not that column) has nothing stored to read, so it is
        // counted here, on the value that was actually persisted.
        $wordCount = $model instanceof Scene && $field === 'contents'
            ? $model->word_count
            : WordCounter::count($storedValue, AutosavableFields::kindOf($entity, $field));

        // This AJAX endpoint only ever records origin: automatic, and skips the write
        // entirely when the persisted value didn't change — so typing something and
        // undoing it leaves no trace. The full-form Save button's permanent, labeled
        // manual checkpoint is recorded separately, server-side, by the entity
        // controllers' update() via RevisionRecorder::recordManualChanges().
        $recorded = $storedValue !== $currentValue
            ? $recorder->record($model, $field, $storedValue, $request->user(), RevisionOrigin::Automatic)
            : null;

        // Coarse trigger (blur/Ctrl-S/submit) only, never a bare debounce tick,
        // and only for Scene.contents. SceneContentsChanged is a published seam
        // for other features. Nothing listens to it today.
        if ($request->boolean('run_matcher') && $model instanceof Scene && $field === 'contents') {
            app(SceneReferenceMatcher::class)->syncScene($model);

            SceneContentsChanged::dispatch($model);
        }

        return response()->json([
            'value' => $storedValue,
            'hash' => hash('sha256', $storedValue),
            'word_count' => $wordCount,
            // record() already returned the row it wrote or coalesced into, so the
            // lookup is only needed for the no-op branch, where the client still
            // wants to know which revision its text currently corresponds to. Reusing
            // it is also the more precise answer: lastRevisionFor() breaks a
            // same-second tie by `created_at` alone, and an autosave burst plus the
            // Save that follows it land in the same second.
            'revision_id' => ($recorded ?? $recorder->lastRevisionFor($model, $field))?->id,
            'saved_at' => now()->toIso8601String(),
        ]);
    }
}
