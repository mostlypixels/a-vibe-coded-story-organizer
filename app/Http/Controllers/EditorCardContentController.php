<?php

namespace App\Http\Controllers;

use App\Models\CodexEntry;
use App\Models\Note;
use App\Services\CodexAsOfResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Returns the body of one card on the Write page as an HTML partial. */
class EditorCardContentController extends Controller
{
    public function codex(Request $request, CodexEntry $codexEntry, CodexAsOfResolver $resolver): View
    {
        $project = $codexEntry->project;
        $this->authorize('view', $project);

        $request->validate(['scene' => ['nullable', 'integer']]);

        // A scene of another project is a 404, so its event never leaks.
        $scene = $request->filled('scene')
            ? $project->sceneQuery()->with('event')->findOrFail($request->integer('scene'))
            : null;
        $moment = $scene?->event;

        return view('editor.partials.codex-card', [
            'project' => $project,
            'row' => $resolver->resolveEntry($codexEntry->load('cover'), $moment),
            'undetermined' => $moment === null,
        ]);
    }

    public function note(Note $note): View
    {
        $this->authorize('view', $note->project);

        return view('editor.partials.note-card', [
            'project' => $note->project,
            'note' => $note->load('category'),
        ]);
    }
}
