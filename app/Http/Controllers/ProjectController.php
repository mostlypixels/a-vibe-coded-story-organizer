<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\CoverImageService;
use App\Services\RecentlyEdited;
use App\Services\SceneReferenceMatcher;
use App\Services\WordCountHistory;
use App\Support\ProjectDeleteWarning;
use App\Support\WriterDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ProjectController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;

    /**
     * Rows in each "Recent" list on the dashboard.
     */
    private const DASHBOARD_LIST_LIMIT = 3;

    public function __construct(
        private CoverImageService $coverImageService,
        private WordCountHistory $history,
    ) {}

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()->route('projects.show', $project);
    }

    public function show(Project $project, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $project);

        // One grouped query for the project total. sceneQuery()
        // already walks chapter -> act -> project (see its own docblock); sum() returns 0
        // rather than NULL when the project has no scenes, but the ?? 0 is kept explicit
        // so this line reads the same "never blank" rule as the withSum sites above.
        $wordCount = $project->sceneQuery()->sum('word_count') ?? 0;

        // Where the writer left off, in two lists. Scenes carry the story: an
        // act or a chapter changes rarely, and a scene row names its act and
        // chapter anyway. The codex list mixes the types, newest first. The top
        // menu reaches everything these two lists leave out. Three rows each,
        // shorter than the section landing pages: the dashboard is a way back
        // into the work, not a list to read.
        $recentScenes = $recentlyEdited->scenes($project, self::DASHBOARD_LIST_LIMIT);
        $recentCodexEntries = $recentlyEdited->allCodexEntries($project, self::DASHBOARD_LIST_LIMIT);

        // The Progress card's chart window: rolling days, not the current month
        // — see expanded/ui.md, "Last 14 rolling days, not the current month"
        // (the window later grew to 31 days; the rolling rule is unchanged).
        // Two queries whatever the range length (see WordCountHistory).
        // auth()->user(), not $project->user: the view policy above already
        // proved they are the same row, and $project->user would cost a third
        // query to fetch a User this request already resolved.
        $today = WriterDay::for(auth()->user());
        $progressSeries = $this->history->series($project, $today->subDays(30), $today);
        $writtenToday = $progressSeries->writtenOn($today);

        // One more query, and only when there is a goal to be consecutive about.
        $writingStreak = $project->daily_word_goal === null
            ? 0
            : $this->history->currentStreak($project, $project->daily_word_goal, $today);

        return view('projects.show', [
            'project' => $project,
            // "View the story" needs a book. The dashboard is project-wide, so it
            // opens the first one.
            'book' => $project->books()->first(),
            // The compact book list card. withCount, not loadCount in a loop —
            // one query for the whole card (CLAUDE.md's N+1 rule).
            'books' => $project->books()->withCount('acts')->get(),
            'wordCount' => $wordCount,
            'recentScenes' => $recentScenes,
            'recentCodexEntries' => $recentCodexEntries,
            'progressSeries' => $progressSeries,
            'writtenToday' => $writtenToday,
            'writingStreak' => $writingStreak,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        $project->loadCount(ProjectDeleteWarning::countRelations());

        return view('projects.edit', [
            'project' => $project,
            'deleteConfirm' => ProjectDeleteWarning::for($project),
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

        return $this->redirectAfterSave($request, ['projects.edit', $project], ['projects.show', $project]);
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
