<?php

namespace App\Http\Controllers;

use App\Enums\RevisionOrigin;
use App\Events\SceneContentsChanged;
use App\Models\Scene;
use App\Services\RevisionRecorder;
use App\Services\SceneReferenceMatcher;
use App\Support\AutosavableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The single HTTP surface every autosaved field goes through (handoff.md §3.1/
 * §9.3) — one action, not one controller per model, because
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

        $storedValue = (string) ($model->fresh()->getAttribute($field) ?? '');

        // This AJAX endpoint only ever records origin: automatic, and skips the write
        // entirely when the persisted value didn't change — so typing something and
        // undoing it leaves no trace. The full-form Save button's permanent, labeled
        // manual checkpoint is recorded separately, server-side, by the entity
        // controllers' update() via RevisionRecorder::recordManualChanges().
        if ($storedValue !== $currentValue) {
            $recorder->record($model, $field, $storedValue, $request->user(), RevisionOrigin::Automatic);
        }

        // Coarse trigger (blur/Ctrl-S/submit) only, never a bare debounce tick, and
        // only for Scene.contents specifically (handoff.md §2.5/§9.10) — this is
        // the published seam .specs/draft/word-count listens to, not something
        // this feature reacts to itself.
        if ($request->boolean('run_matcher') && $model instanceof Scene && $field === 'contents') {
            app(SceneReferenceMatcher::class)->syncScene($model);

            SceneContentsChanged::dispatch($model);
        }

        return response()->json([
            'value' => $storedValue,
            'hash' => hash('sha256', $storedValue),
            'revision_id' => $recorder->lastRevisionFor($model, $field)?->id,
            'saved_at' => now()->toIso8601String(),
        ]);
    }
}
