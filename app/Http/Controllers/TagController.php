<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Manages a project's tag vocabulary. Tags are also born inline on the codex
 * entry form; this screen renames and removes them, and shows how many entries
 * each one covers.
 */
class TagController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('tags.index', [
            'project' => $project,
            'tags' => $project->tags()->withCount('entries')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTagRequest $request, Project $project): RedirectResponse
    {
        $project->tags()->create($request->validated());

        return redirect()->route('projects.tags.index', $project);
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()->route('projects.tags.index', $tag->project);
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag->project);

        $project = $tag->project;

        // The codex_entry_tag rows cascade, so deleting a tag detaches it from
        // every entry that carries it.
        $tag->delete();

        return redirect()->route('projects.tags.index', $project);
    }
}
