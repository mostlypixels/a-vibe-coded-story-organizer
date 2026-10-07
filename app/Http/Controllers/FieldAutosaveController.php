<?php

namespace App\Http\Controllers;

use App\Exceptions\RevisionConflictException;
use App\Http\Requests\AutosaveFieldRequest;
use App\Models\Scene;
use App\Services\FieldAutosaver;
use App\Services\ReferencingScenes;
use App\Support\AutosavableFields;
use App\Support\FieldHash;
use Illuminate\Http\JsonResponse;

/**
 * The single HTTP surface every autosaved field goes through — one action, not
 * one controller per model, because
 * {@see AutosavableFields::REGISTRY} is the only place a `{entity}` slug resolves
 * to a model+field. An unregistered slug never reaches this class at all: the
 * router's `->whereIn('entity', AutosavableFields::slugs())` 404s it first.
 */
class FieldAutosaveController extends Controller
{
    public function update(AutosaveFieldRequest $request, string $entity, int $id, string $field, FieldAutosaver $autosaver, ReferencingScenes $referencingScenes): JsonResponse
    {
        $model = $request->autosavable();
        $this->authorize('update', $model->revisionProject());

        try {
            $result = $autosaver->save(
                $model,
                $field,
                $request->validated('value'),
                $request->validated('base_hash'),
                $request->user(),
                $request->boolean('run_matcher'),
                $request->boolean('new_revision'),
            );
        } catch (RevisionConflictException) {
            // The client offers "Load saved text" and needs the hash to save over it.
            $storedValue = (string) ($model->getAttribute($field) ?? '');

            return response()->json([
                'message' => __('This field was changed elsewhere.'),
                'value' => $storedValue,
                'hash' => FieldHash::of($storedValue),
            ], 409);
        }

        $payload = [
            'value' => $result->value,
            'hash' => $result->hash(),
            'word_count' => $result->wordCount,
            'revision_id' => $result->revisionId,
            'saved_at' => now()->toIso8601String(),
        ];

        // The scene editor shows this list. The same partial renders it on page load.
        if ($result->referencesSynced && $model instanceof Scene) {
            $payload['referenced_entries_html'] = view('codex.partials.referenced-entries', [
                'referencedEntries' => $referencingScenes->forScene($model),
                'scene' => $model,
            ])->render();
        }

        return response()->json($payload);
    }
}
