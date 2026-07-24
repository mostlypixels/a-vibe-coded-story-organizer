<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\CoverImageService;
use App\Services\SceneReferenceMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Throwable;

class ProjectController extends Controller
{
    use RecordsManualRevisions;

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

        // Counts feed the delete-confirm string below. The main plotline and the
        // Start/End bookend events are auto-created, un-deletable invariants of every
        // project (see Project::booted()) — they are excluded so a brand-new project
        // reads as having nothing to lose, not "1 plotline and 2 events".
        $project->loadCount([
            'acts',
            'plotlines' => fn ($query) => $query->where('is_main', false),
            'events' => fn ($query) => $query->where('is_fixed', false),
            'codexEntries',
        ]);

        return view('projects.edit', [
            'project' => $project,
            'deleteConfirm' => $this->buildDeleteConfirmMessage($project),
        ]);
    }

    /**
     * Build the native confirm() string for deleting this project: a plain
     * cascade-count sentence listing only the non-zero categories among its direct
     * children, e.g. "This project has 2 acts and 5 codex entries, which will also be
     * deleted." A brand-new project (nothing beyond its un-deletable main plotline and
     * bookend events) falls back to the original unqualified question — there is
     * nothing to enumerate that the user doesn't already expect to lose.
     */
    private function buildDeleteConfirmMessage(Project $project): string
    {
        $categories = [
            'acts' => [$project->acts_count, '{1} :count act|[2,*] :count acts'],
            'plotlines' => [$project->plotlines_count, '{1} :count plotline|[2,*] :count plotlines'],
            'events' => [$project->events_count, '{1} :count event|[2,*] :count events'],
            'codex entries' => [$project->codex_entries_count, '{1} :count codex entry|[2,*] :count codex entries'],
        ];

        $nonZero = [];

        foreach ($categories as [$count, $choicePattern]) {
            if ($count > 0) {
                $nonZero[] = trans_choice($choicePattern, $count, ['count' => $count]);
            }
        }

        if ($nonZero === []) {
            return __('Are you sure you want to delete this project?');
        }

        return __('This project has :categories, which will also be deleted.', [
            'categories' => Arr::join($nonZero, ', ', ' '.__('and').' '),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox) out of the plain attribute update and resolve it separately.
        $data = $request->safe()->except(['cover_image', 'remove_cover_image']);

        // Snapshot before the update below overwrites these in memory — see
        // RecordsManualRevisions::snapshotAutosaved()'s docblock.
        $beforeAutosavedFields = $this->snapshotAutosaved($project, $data);

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

        $this->recordManualSave($project, $beforeAutosavedFields);

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
