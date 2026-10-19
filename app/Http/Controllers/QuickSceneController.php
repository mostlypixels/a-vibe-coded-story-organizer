<?php

namespace App\Http\Controllers;

use App\Enums\SceneStatus;
use App\Http\Requests\StoreQuickSceneRequest;
use App\Models\Book;
use App\Services\SceneSaver;
use Illuminate\Http\JsonResponse;

/**
 * The AJAX counterpart to the New Scene page, for the Write page dialog.
 * The JS flushes autosave before it opens the returned URL.
 */
class QuickSceneController extends Controller
{
    public function store(StoreQuickSceneRequest $request, Book $book, SceneSaver $saver): JsonResponse
    {
        $validated = $request->validated();
        $chapter = $book->chapterQuery()->findOrFail($validated['chapter_id']);

        $scene = $saver->create($chapter, [
            'name' => $validated['name'],
            'status' => SceneStatus::Draft->value,
        ]);

        return response()->json(['url' => route('scenes.write', $scene)], 201);
    }
}
