<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ReparentsChildren;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DestroyChapterRequest;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Services\CoverImageService;
use App\Support\StoryNumbering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ChapterController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ReparentsChildren;
    use ResolvesIndexSorting;

    public function __construct(private CoverImageService $coverImageService) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        [$sort, $direction] = $this->resolveSorting($request, ['name', 'position'], 'position');

        $chapters = $project->chapterQuery()
            // Joined so the `#` column can sort by story order (act order, then position
            // within the act). Grouping by `act_id` instead — as this did — only matches
            // story order until someone reorders an act. Because `acts` carries `name` and
            // `position` columns of its own, every column below is table-qualified: the
            // join makes bare `name`/`position` ambiguous (see Project::chapterQuery()).
            ->join('acts', 'acts.id', '=', 'chapters.act_id')
            ->with('act')
            ->withCount('scenes')
            // One grouped query for the whole page (word-count spec, task 9) — never a
            // per-row sum() in the view, which would be an N+1 over the chapter list.
            // Both aggregates add `chapters.*` themselves, so this query must never gain
            // a select() *after* them: that resets the column list and drops their aliases.
            ->withSum('scenes as word_count', 'word_count')
            ->when($request->filled('search'), fn ($query) => $query->where('chapters.name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('act'), fn ($query) => $query->where('chapters.act_id', $request->query('act')))
            ->when(
                $sort === 'position',
                // $direction is applied to every key, so descending reads as the story
                // backwards rather than acts ascending with chapters reversed inside them.
                // The id tie-breaks are part of the contract: `position` has no unique
                // constraint, so two siblings can share one and must still order stably.
                fn ($query) => $query
                    ->orderBy('acts.position', $direction)
                    ->orderBy('acts.id', $direction)
                    ->orderBy('chapters.position', $direction)
                    ->orderBy('chapters.id', $direction),
                // $sort is allow-listed by resolveSorting(), so it is safe to qualify.
                fn ($query) => $query->orderBy('chapters.'.$sort, $direction)
            )
            ->get();

        // withSum leaves word_count as NULL for a chapter with no scenes (SQL SUM has
        // no rows to sum). A chapter with no scenes renders "0 words", not blank.
        foreach ($chapters as $chapter) {
            $chapter->word_count ??= 0;
        }

        // The delete-with-move dialog on each row needs the full set of the project's
        // chapters as move destinations, independent of the current search/act filter
        // above (moving is never limited to what the filter happens to match).
        $destinationChapters = $project->chapterQuery()
            ->orderBy('act_id')
            ->orderBy('position')
            ->get(['id', 'name', 'act_id']);

        return view('chapters.index', [
            'project' => $project->load('acts'),
            'chapters' => $chapters,
            'destinationChapters' => $destinationChapters,
            'sort' => $sort,
            'direction' => $direction,
            // Built from the whole project, never the filtered/paginated $chapters
            // above — a chapters list filtered to one act must still start counting
            // from that act's true project-wide number.
            'numbering' => StoryNumbering::forProject($project),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('chapters.create', ['project' => $project->load('acts')]);
    }

    public function store(StoreChapterRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $act = $project->acts()->findOrFail($validated['act_id']);

        $act->chapters()->create(collect($validated)->except('act_id')->all());

        return redirect()->route('projects.chapters.index', $project);
    }

    public function edit(Chapter $chapter): View
    {
        $this->authorize('update', $chapter->act->project);

        $project = $chapter->act->project;

        // Feeds the delete-with-move dialog's honest cascade summary: a chapter is a
        // one-level entity, so only its direct children (scenes) are counted.
        $chapter->loadCount('scenes');

        // Every *other* chapter in the project is a candidate destination for moving
        // this chapter's scenes. An empty list collapses the dialog to "delete
        // everything".
        $destinations = $project->chapterQuery()
            ->where('id', '!=', $chapter->id)
            ->orderBy('act_id')
            ->orderBy('position')
            ->get();

        // This chapter's rank among its act's siblings, for the "2 of 5" half of the
        // position hint — a gap-free rank, not the raw (possibly gappy) `position`
        // column. Same (position, id) tie-break as StoryNumbering, one level deep.
        $siblingIds = $chapter->act->chapters()->orderBy('position')->orderBy('id')->pluck('id');

        return view('chapters.edit', [
            'chapter' => $chapter,
            'project' => $project->load('acts'),
            'destinations' => $destinations,
            'numbering' => StoryNumbering::forProject($project),
            'positionInAct' => $siblingIds->search($chapter->id) + 1,
            'totalInAct' => $siblingIds->count(),
        ]);
    }

    public function update(UpdateChapterRequest $request, Chapter $chapter): RedirectResponse
    {
        $project = $chapter->act->project;
        $act = $project->acts()->findOrFail($request->validated()['act_id']);

        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox and the non-fillable act_id) out of the plain attribute fill.
        $data = $request->safe()->except(['act_id', 'cover_image', 'remove_cover_image']);

        // Snapshot before fill()/save() below overwrite these in memory — see
        // RecordsManualRevisions::snapshotAutosaved()'s docblock.
        $beforeAutosavedFields = $this->snapshotAutosaved($chapter, $data);

        // The previous file is only unlinked *after* a successful save, so a failed
        // write never leaves the row pointing at a file we already deleted.
        $previousCover = $chapter->cover_image;
        $storedCover = null;

        if ($request->hasFile('cover_image')) {
            $storedCover = $this->coverImageService->store(
                $request->file('cover_image'),
                CoverImageService::CHAPTER_COVER_DIRECTORY
            );
            $data['cover_image'] = $storedCover;
        } elseif ($request->boolean('remove_cover_image')) {
            $data['cover_image'] = null;
        }

        // act_id is intentionally not mass-assignable (see Chapter::$fillable), so
        // reparent through the relationship rather than the (silently ignored)
        // fillable array — otherwise moving a chapter to another act is a no-op.
        $chapter->fill($data);
        $chapter->act()->associate($act);

        try {
            $chapter->save();
        } catch (Throwable $exception) {
            // The row write failed after the new file landed — unlink it before
            // rethrowing so the failure never leaves an orphan file behind.
            $this->coverImageService->delete($storedCover);

            throw $exception;
        }

        // A new upload replaces the old file; the remove checkbox clears it. Either way
        // the previous file is now safe to delete post-commit.
        if ($storedCover !== null || $request->boolean('remove_cover_image')) {
            $this->coverImageService->delete($previousCover);
        }

        $this->recordManualSave($chapter, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['chapters.edit', $chapter], ['projects.chapters.index', $project]);
    }

    public function destroy(DestroyChapterRequest $request, Chapter $chapter): RedirectResponse
    {
        // Authorization is handled by DestroyChapterRequest::authorize() (mirrors the
        // walk-up-to-project check the other actions perform).
        $project = $chapter->act->project;

        // Reassignment (optional) and the delete itself are a single atomic unit: a
        // failure partway must never leave scenes half-moved or an orphaned chapter
        // (CLAUDE.md's multi-step-write transaction rule).
        DB::transaction(function () use ($request, $chapter, $project) {
            if ($destinationId = $request->validated('move_children_to')) {
                $destination = $project->chapterQuery()->findOrFail($destinationId);

                $this->reparentChildren($chapter, $destination, 'scenes', 'chapter');
            }

            // Same cascade path as before — just nothing left to cascade if the
            // scenes were reassigned above.
            $chapter->delete();
        });

        return redirect()->route('projects.chapters.index', $project);
    }

    public function moveUp(Chapter $chapter): RedirectResponse
    {
        $this->reorderSibling($chapter, $chapter->act->project, up: true);

        return redirect()->back();
    }

    public function moveDown(Chapter $chapter): RedirectResponse
    {
        $this->reorderSibling($chapter, $chapter->act->project, up: false);

        return redirect()->back();
    }
}
