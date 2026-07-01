<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['name', 'position']) ? $request->query('sort') : 'position';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $scenes = Scene::query()
            ->whereHas('chapter.act', fn ($query) => $query->where('project_id', $project->id))
            ->with('chapter.act')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('chapter'), fn ($query) => $query->where('chapter_id', $request->query('chapter')))
            ->when($sort === 'position', fn ($query) => $query->orderBy('chapter_id'))
            ->orderBy($sort, $direction)
            ->get();

        return view('scenes.index', [
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
            'scenes' => $scenes,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('scenes.create', [
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
        ]);
    }

    public function store(StoreSceneRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $chapter = $this->chapterQueryFor($project)->findOrFail($validated['chapter_id']);

        $chapter->scenes()->create(collect($validated)->except('chapter_id')->all());

        return redirect()->route('projects.scenes.index', $project);
    }

    public function edit(Scene $scene): View
    {
        $project = $scene->chapter->act->project;

        $this->authorize('update', $project);

        return view('scenes.edit', [
            'scene' => $scene,
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
        ]);
    }

    public function update(UpdateSceneRequest $request, Scene $scene): RedirectResponse
    {
        $project = $scene->chapter->act->project;
        $validated = $request->validated();
        $chapter = $this->chapterQueryFor($project)->findOrFail($validated['chapter_id']);

        $scene->update(collect($validated)->except('chapter_id')->all() + ['chapter_id' => $chapter->id]);

        return redirect()->route('projects.scenes.index', $project);
    }

    public function destroy(Scene $scene): RedirectResponse
    {
        $project = $scene->chapter->act->project;

        $this->authorize('update', $project);

        $scene->delete();

        return redirect()->route('projects.scenes.index', $project);
    }

    public function moveUp(Scene $scene): RedirectResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $this->swapPosition($scene, '<', 'desc');

        return redirect()->back();
    }

    public function moveDown(Scene $scene): RedirectResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $this->swapPosition($scene, '>', 'asc');

        return redirect()->back();
    }

    private function swapPosition(Scene $scene, string $operator, string $direction): void
    {
        $sibling = Scene::where('chapter_id', $scene->chapter_id)
            ->where('position', $operator, $scene->position)
            ->orderBy('position', $direction)
            ->first();

        if ($sibling) {
            [$scene->position, $sibling->position] = [$sibling->position, $scene->position];
            $scene->save();
            $sibling->save();
        }
    }

    private function chapterQueryFor(Project $project): Builder
    {
        return Chapter::query()
            ->whereHas('act', fn ($query) => $query->where('project_id', $project->id));
    }

    private function chaptersFor(Project $project): Collection
    {
        return $this->chapterQueryFor($project)
            ->with('act')
            ->orderBy('name')
            ->get();
    }
}
