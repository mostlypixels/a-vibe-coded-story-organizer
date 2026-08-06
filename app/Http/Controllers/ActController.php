<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ReparentsChildren;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DestroyActRequest;
use App\Http\Requests\StoreActRequest;
use App\Http\Requests\UpdateActRequest;
use App\Models\Act;
use App\Models\Project;
use App\Support\StoryNumbering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ReparentsChildren;
    use ResolvesIndexSorting;

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        [$sort, $direction] = $this->resolveSorting($request, ['name', 'position'], 'position');

        $acts = $project->acts()
            ->withCount('chapters')
            // One grouped query for the whole page, via the act's own scenes()
            // HasManyThrough — a dot-nested relation path like 'chapters.scenes'
            // is not a real relation name and throws BadMethodCallException, so
            // it must go through that relation directly.
            ->withSum('scenes as word_count', 'word_count')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->orderBy($sort, $direction)
            ->get();

        // withSum leaves word_count as NULL for an act with no scenes (SQL SUM has no
        // rows to sum). An act with no scenes renders "0 words", not blank.
        foreach ($acts as $act) {
            $act->word_count ??= 0;
        }

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
            // Built from the whole project, never the (possibly search-filtered)
            // $acts above — a filtered acts list must still show its true numbers.
            'numbering' => StoryNumbering::forProject($project),
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
        $sceneCount = $act->scenes()->count();

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
            'numbering' => StoryNumbering::forProject($act->project),
            'totalActs' => $act->project->acts()->count(),
        ]);
    }

    public function update(UpdateActRequest $request, Act $act): RedirectResponse
    {
        $data = $request->validated();
        $beforeAutosavedFields = $this->snapshotAutosaved($act, $data);

        $act->update($data);

        $this->recordManualSave($act, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['acts.edit', $act], ['projects.acts.index', $act->project]);
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

                $this->reparentChildren($act, $destination, 'chapters', 'act');
            }

            // Same cascade path as before — just nothing left to cascade if the
            // chapters were reassigned above.
            $act->delete();
        });

        return redirect()->route('projects.acts.index', $project);
    }

    public function moveUp(Act $act): RedirectResponse
    {
        $this->reorderSibling($act, $act->project, up: true);

        return redirect()->back();
    }

    public function moveDown(Act $act): RedirectResponse
    {
        $this->reorderSibling($act, $act->project, up: false);

        return redirect()->back();
    }
}
