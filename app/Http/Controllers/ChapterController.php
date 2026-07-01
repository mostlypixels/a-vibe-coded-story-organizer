<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name']) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $chapters = Chapter::query()
            ->whereHas('act', fn ($query) => $query->where('project_id', $project->id))
            ->with('act')
            ->withCount('scenes')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('act'), fn ($query) => $query->where('act_id', $request->query('act')))
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
        $validated = $request->validated();
        $act = $project->acts()->findOrFail($validated['act_id']);

        $chapter->update(collect($validated)->except('act_id')->all() + ['act_id' => $act->id]);

        return redirect()->route('projects.chapters.index', $project);
    }

    public function destroy(Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $chapter->act->project);

        $project = $chapter->act->project;
        $chapter->delete();

        return redirect()->route('projects.chapters.index', $project);
    }
}
