<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActRequest;
use App\Http\Requests\UpdateActRequest;
use App\Models\Act;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name']) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $acts = $project->acts()
            ->withCount('chapters')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->orderBy($sort, $direction)
            ->get();

        return view('acts.index', [
            'project' => $project,
            'acts' => $acts,
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

        return view('acts.edit', ['act' => $act]);
    }

    public function update(UpdateActRequest $request, Act $act): RedirectResponse
    {
        $act->update($request->validated());

        return redirect()->route('projects.acts.index', $act->project);
    }

    public function destroy(Act $act): RedirectResponse
    {
        $this->authorize('update', $act->project);

        $project = $act->project;
        $act->delete();

        return redirect()->route('projects.acts.index', $project);
    }
}
