<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Http\Requests\StoreQuickCodexEntryRequest;
use App\Models\Scene;
use App\Services\CodexEntrySaver;
use App\Services\ReferencingScenes;
use App\Services\SceneReferenceMatcher;
use App\Support\CodexMediaUploads;
use Illuminate\Http\JsonResponse;

/**
 * Creates a name-and-type codex entry from the scene editor, without the project-wide
 * rescan the full form pays: only the one scene that prompted the entry is resynced.
 * {@see CodexEntrySaver::create()}'s `$rescanProject` flag is what buys that skip.
 */
class SceneCodexEntryController extends Controller
{
    public function store(
        StoreQuickCodexEntryRequest $request,
        Scene $scene,
        CodexEntrySaver $saver,
        SceneReferenceMatcher $matcher,
        ReferencingScenes $referencingScenes,
    ): JsonResponse {
        $project = $scene->chapter->act->book->project;

        $entry = $saver->create(
            $project,
            CodexEntryType::from($request->validated('type')),
            ['name' => $request->validated('name')],
            new CodexMediaUploads,
            rescanProject: false,
        );

        $matcher->syncScene($scene);

        $referencedEntries = $referencingScenes->forScene($scene);

        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'name' => $entry->name,
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'url' => route('codex.show', $entry),
            ],
            'referenced_entries_html' => view('codex.partials.referenced-entries', [
                'referencedEntries' => $referencedEntries,
                'scene' => $scene,
            ])->render(),
        ]);
    }
}
