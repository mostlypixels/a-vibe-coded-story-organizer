<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlotlineRequest;
use App\Http\Requests\UpdatePlotlineRequest;
use App\Models\Plotline;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlotlineController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name', 'color']) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $plotlines = $project->plotlines()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->orderBy($sort, $direction)
            ->get();

        return view('plotlines.index', [
            'project' => $project,
            'plotlines' => $plotlines,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('plotlines.create', ['project' => $project]);
    }

    public function store(StorePlotlineRequest $request, Project $project): RedirectResponse
    {
        $project->plotlines()->create($request->validated());

        return redirect()->route('projects.plotlines.index', $project);
    }

    public function edit(Plotline $plotline): View
    {
        $this->authorize('update', $plotline->project);

        return view('plotlines.edit', ['plotline' => $plotline]);
    }

    public function update(UpdatePlotlineRequest $request, Plotline $plotline): RedirectResponse
    {
        $plotline->update($request->validated());

        return redirect()->route('projects.plotlines.index', $plotline->project);
    }

    public function destroy(Plotline $plotline): RedirectResponse
    {
        $this->authorize('update', $plotline->project);

        abort_if($plotline->is_main, 403);

        $project = $plotline->project;
        $plotline->delete();

        return redirect()->route('projects.plotlines.index', $project);
    }
}
