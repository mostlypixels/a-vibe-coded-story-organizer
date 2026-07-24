<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyActRequest;
use App\Http\Requests\StoreActRequest;
use App\Http\Requests\UpdateActRequest;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name', 'position']) ? $request->query('sort') : 'position';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $acts = $project->acts()
            ->withCount('chapters')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->orderBy($sort, $direction)
            ->get();

        // The delete-with-move dialog on each row needs the full set of sibling acts as
        // move destinations, independent of the current search filter above (moving is
        // never limited to what the search happens to match).
        $destinationActs = $project->acts()->orderBy('position')->get(['id', 'name', 'position']);

        return view('acts.index', [
            'project' => $project,
            'acts' => $acts,
            'destinationActs' => $destinationActs,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('acts.create', ['project' => $project]);
    }

    public function store(StoreActRequest $request, Project $project): RedirectResponse
    {
        $project->acts()->create($request->validated());

        return redirect()->route('projects.acts.index', $project);
    }

    public function edit(Act $act): View
    {
        $this->authorize('update', $act->project);

        // Counts feed the delete-with-move dialog's honest cascade summary: an act's
        // direct children (chapters) plus its grandchildren (scenes, counted through
        // the chapters) — both are destroyed by a plain cascade delete.
        $act->loadCount('chapters');
        $sceneCount = Scene::whereHas('chapter', fn ($query) => $query->where('act_id', $act->id))->count();

        // Every *other* act in the project is a candidate destination for moving this
        // act's chapters. An empty list collapses the dialog to "delete everything".
        $destinations = $act->project->acts()
            ->where('id', '!=', $act->id)
            ->orderBy('position')
            ->get();

        return view('acts.edit', [
            'act' => $act,
            'sceneCount' => $sceneCount,
            'destinations' => $destinations,
        ]);
    }

    public function update(UpdateActRequest $request, Act $act): RedirectResponse
    {
        $act->update($request->validated());

        return $request->boolean('stay')
            ? redirect()->route('acts.edit', $act)->with('status', 'saved')
            : redirect()->route('projects.acts.index', $act->project);
    }

    public function destroy(DestroyActRequest $request, Act $act): RedirectResponse
    {
        // Authorization is handled by DestroyActRequest::authorize() (mirrors the
        // walk-up-to-project check the other actions perform).
        $project = $act->project;

        // Reassignment (optional) and the delete itself are a single atomic unit: a
        // failure partway must never leave chapters half-moved or an orphaned act
        // (CLAUDE.md's multi-step-write transaction rule).
        DB::transaction(function () use ($request, $act) {
            if ($destinationId = $request->validated('move_children_to')) {
                $destination = $act->project->acts()->findOrFail($destinationId);

                // Chapter::booted() only assigns `position` on create, never on a plain
                // act_id update, so we set it explicitly here — appending each moved
                // chapter after the destination's existing ones, in ascending original
                // order, so their relative order survives the move and no two chapters
                // ever share a position within the destination act.
                $nextPosition = $destination->chapters()->max('position') + 1;

                $act->chapters()->orderBy('position')->get()->each(function (Chapter $chapter) use ($destination, &$nextPosition) {
                    // act_id is intentionally not mass-assignable (see Chapter::$fillable),
                    // so reparent through the relationship — a plain update(['act_id' => …])
                    // is silently dropped. Mirrors ChapterController::update()'s associate().
                    $chapter->position = $nextPosition++;
                    $chapter->act()->associate($destination);
                    $chapter->save();
                });
            }

            // Same cascade path as before — just nothing left to cascade if the
            // chapters were reassigned above.
            $act->delete();
        });

        return redirect()->route('projects.acts.index', $project);
    }

    public function moveUp(Act $act): RedirectResponse
    {
        $this->authorize('update', $act->project);

        $act->moveUp();

        return redirect()->back();
    }

    public function moveDown(Act $act): RedirectResponse
    {
        $this->authorize('update', $act->project);

        $act->moveDown();

        return redirect()->back();
    }
}
