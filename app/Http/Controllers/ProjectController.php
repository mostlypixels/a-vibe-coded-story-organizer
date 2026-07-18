<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\CoverImageService;
use App\Services\SceneReferenceMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ProjectController extends Controller
{
    public function __construct(private CoverImageService $coverImageService) {}

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()->route('projects.show', $project);
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->loadCount(['plotlines', 'events']);

        return view('projects.show', ['project' => $project]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox) out of the plain attribute update and resolve it separately.
        $data = $request->safe()->except(['cover_image', 'remove_cover_image']);

        // The previous file is only unlinked *after* a successful save, so a failed
        // write never leaves the row pointing at a file we already deleted.
        $previousCover = $project->cover_image;
        $storedCover = null;

        if ($request->hasFile('cover_image')) {
            $storedCover = $this->coverImageService->store(
                $request->file('cover_image'),
                CoverImageService::PROJECT_COVER_DIRECTORY
            );
            $data['cover_image'] = $storedCover;
        } elseif ($request->boolean('remove_cover_image')) {
            $data['cover_image'] = null;
        }

        try {
            $project->update($data);
        } catch (Throwable $exception) {
            // The row write failed after the new file landed — unlink it before
            // rethrowing so the failure never leaves an orphan file behind
            // (mirrors CodexMediaService::store()'s store-then-unlink pattern).
            $this->coverImageService->delete($storedCover);

            throw $exception;
        }

        // A new upload replaces the old file; the remove checkbox clears it. Either way
        // the previous file is now safe to delete post-commit.
        if ($storedCover !== null || $request->boolean('remove_cover_image')) {
            $this->coverImageService->delete($previousCover);
        }

        return $request->boolean('stay')
            ? redirect()->route('projects.edit', $project)->with('status', 'saved')
            : redirect()->route('projects.show', $project);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('dashboard');
    }

    /**
     * Manual escape hatch for the derived `scene_codex_entry` pivot: rebuild every scene's
     * codex entry references for this project from scratch. Normal saves keep the pivot in
     * sync automatically (SceneReferenceMatcher); this exists for pre-existing data that
     * predates the feature, or if the pivot is ever suspected to have drifted. Same
     * `update` authorization as the rest of project editing — it's a project-scoped write,
     * not a destructive one, but it isn't part of the main edit form's own data.
     */
    public function syncCodexReferences(Project $project, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $this->authorize('update', $project);

        $matcher->syncProject($project);

        return redirect()->route('projects.edit', $project)->with('status', 'codex-references-synced');
    }
}
