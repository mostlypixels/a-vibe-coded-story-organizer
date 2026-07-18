<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Services\CoverImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ChapterController extends Controller
{
    public function __construct(private CoverImageService $coverImageService) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name', 'position']) ? $request->query('sort') : 'position';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $chapters = Chapter::query()
            ->whereHas('act', fn ($query) => $query->where('project_id', $project->id))
            ->with('act')
            ->withCount('scenes')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('act'), fn ($query) => $query->where('act_id', $request->query('act')))
            ->when($sort === 'position', fn ($query) => $query->orderBy('act_id'))
            ->orderBy($sort, $direction)
            ->get();

        return view('chapters.index', [
            'project' => $project->load('acts'),
            'chapters' => $chapters,
            'sort' => $sort,
            'direction' => $direction,
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

        return view('chapters.edit', [
            'chapter' => $chapter,
            'project' => $chapter->act->project->load('acts'),
        ]);
    }

    public function update(UpdateChapterRequest $request, Chapter $chapter): RedirectResponse
    {
        $project = $chapter->act->project;
        $act = $project->acts()->findOrFail($request->validated()['act_id']);

        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox and the non-fillable act_id) out of the plain attribute fill.
        $data = $request->safe()->except(['act_id', 'cover_image', 'remove_cover_image']);

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

        return $request->boolean('stay')
            ? redirect()->route('chapters.edit', $chapter)->with('status', 'saved')
            : redirect()->route('projects.chapters.index', $project);
    }

    public function destroy(Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $chapter->act->project);

        $project = $chapter->act->project;
        $chapter->delete();

        return redirect()->route('projects.chapters.index', $project);
    }

    public function moveUp(Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $chapter->act->project);

        $chapter->moveUp();

        return redirect()->back();
    }

    public function moveDown(Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $chapter->act->project);

        $chapter->moveDown();

        return redirect()->back();
    }
}
