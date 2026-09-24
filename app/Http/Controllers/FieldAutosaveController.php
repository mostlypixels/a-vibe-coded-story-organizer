<?php

namespace App\Http\Controllers;

use App\Exceptions\RevisionConflictException;
use App\Http\Requests\AutosaveFieldRequest;
use App\Services\FieldAutosaver;
use App\Support\AutosavableFields;
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
    public function update(AutosaveFieldRequest $request, string $entity, int $id, string $field, FieldAutosaver $autosaver): JsonResponse
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
            );
        } catch (RevisionConflictException) {
            return response()->json(['message' => __('This field was changed elsewhere.')], 409);
        }

        return response()->json([
            'value' => $result->value,
            'hash' => $result->hash(),
            'word_count' => $result->wordCount,
            'revision_id' => $result->revisionId,
            'saved_at' => now()->toIso8601String(),
        ]);
    }
}
