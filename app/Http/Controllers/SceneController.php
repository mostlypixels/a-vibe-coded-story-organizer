<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Scene;
use App\Services\CodexAsOfResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
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
            ->with('chapter.act', 'event')
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
            'events' => $this->eventsFor($project),
            // Bounds for the inline "New event" datetime — always a regular event, so it
            // sits inside [Start, End] (mirrors WithinEventWindow; server stays authoritative).
            'windowMin' => $project->startEvent()->event_datetime->format('Y-m-d\TH:i'),
            'windowMax' => $project->endEvent()->event_datetime->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(StoreSceneRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $chapter = $this->chapterQueryFor($project)->findOrFail($validated['chapter_id']);

        $scene = $chapter->scenes()->create(
            $this->sceneAttributes($validated) + ['event_id' => $this->resolveHappensDuringEvent($project, $validated)]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        return redirect()->route('projects.scenes.index', $project);
    }

    public function edit(Scene $scene, CodexAsOfResolver $codexAsOf): View
    {
        $project = $scene->chapter->act->project;

        $this->authorize('update', $project);

        $scene->load('event', 'mentionedEvents');

        return view('scenes.edit', [
            'scene' => $scene,
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
            'events' => $this->eventsFor($project),
            'windowMin' => $project->startEvent()->event_datetime->format('Y-m-d\TH:i'),
            'windowMax' => $project->endEvent()->event_datetime->format('Y-m-d\TH:i'),
            // Codex values resolved as of the scene's "happens during" event (null when the
            // scene is unassigned → the panel shows the undetermined state). Pre-computed here
            // so no timeline math or N+1 resolution happens in Blade.
            'codexAsOfGroups' => $codexAsOf->resolve($project, $scene->event),
        ]);
    }

    public function update(UpdateSceneRequest $request, Scene $scene): RedirectResponse
    {
        $project = $scene->chapter->act->project;
        $validated = $request->validated();
        $chapter = $this->chapterQueryFor($project)->findOrFail($validated['chapter_id']);

        $scene->update(
            $this->sceneAttributes($validated)
            + ['chapter_id' => $chapter->id, 'event_id' => $this->resolveHappensDuringEvent($project, $validated)]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        return redirect()->route('projects.scenes.index', $project);
    }

    public function destroy(Scene $scene): RedirectResponse
    {
        $project = $scene->chapter->act->project;

        $this->authorize('update', $project);

        $scene->delete();

        return redirect()->route('projects.scenes.index', $project);
    }

    public function moveUp(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $scene->moveUp();

        if ($request->wantsJson()) {
            return response()->json(['position' => $scene->position]);
        }

        return redirect()->back();
    }

    public function moveDown(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $scene->chapter->act->project);

        $scene->moveDown();

        if ($request->wantsJson()) {
            return response()->json(['position' => $scene->position]);
        }

        return redirect()->back();
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

    private function eventsFor(Project $project): Collection
    {
        return $project->events()->orderBy('event_datetime')->get();
    }

    /**
     * Scene column values, stripped of the relationship/form-only keys handled separately.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sceneAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['chapter_id', 'event_id', 'new_event_title', 'new_event_datetime', 'mentioned_events'])
            ->all();
    }

    /**
     * Resolve the "happens during" event id: create a new event from the inline form when
     * provided (auto-attached to the main plotline), otherwise use the selected event (may
     * be null, leaving the scene unassigned).
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveHappensDuringEvent(Project $project, array $validated): ?int
    {
        if (! empty($validated['new_event_title'])) {
            $event = $project->events()->create([
                'title' => $validated['new_event_title'],
                'event_datetime' => $validated['new_event_datetime'],
            ]);

            $event->plotlines()->attach($project->plotlines()->where('is_main', true)->value('id'));

            return $event->id;
        }

        return $validated['event_id'] ?? null;
    }
}
