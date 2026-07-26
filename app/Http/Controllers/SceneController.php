<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\StoreSceneRequest;
use App\Http\Requests\UpdateSceneRequest;
use App\Models\Project;
use App\Models\Scene;
use App\Services\CodexAsOfResolver;
use App\Services\SceneReferenceMatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SceneController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ResolvesIndexSorting;

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        [$sort, $direction] = $this->resolveSorting($request, ['name', 'position'], 'position');

        $scenes = $project->sceneQuery()
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

    public function store(StoreSceneRequest $request, Project $project, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $validated = $request->validated();
        $chapter = $project->chapterQuery()->findOrFail($validated['chapter_id']);

        $scene = $chapter->scenes()->create(
            $this->sceneAttributes($validated) + ['event_id' => $this->resolveHappensDuringEvent($project, $validated)]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        // Recompute which codex entries this scene's contents reference. A save always
        // resyncs the single scene (no "did contents change" skip — contents changing is
        // the point), mirroring the mentionedEvents()->sync() call above.
        $matcher->syncScene($scene);

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
            // Codex entries whose name/alias whole-word-matches this scene's contents, as of
            // the last save. A read-only view of the scene_codex_entry pivot maintained by
            // SceneReferenceMatcher — the sidebar renders this flat list ordered by (type, name).
            'referencedEntries' => $scene->codexReferences()->with('cover')->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateSceneRequest $request, Scene $scene, SceneReferenceMatcher $matcher): RedirectResponse
    {
        $project = $scene->chapter->act->project;
        $validated = $request->validated();
        $chapter = $project->chapterQuery()->findOrFail($validated['chapter_id']);
        $sceneAttributes = $this->sceneAttributes($validated);

        $beforeAutosavedFields = $this->snapshotAutosaved($scene, $sceneAttributes);

        $scene->update(
            $sceneAttributes
            + ['chapter_id' => $chapter->id, 'event_id' => $this->resolveHappensDuringEvent($project, $validated)]
        );

        $scene->mentionedEvents()->sync($validated['mentioned_events'] ?? []);

        // Recompute references against the scene's now-saved contents (see store()).
        $matcher->syncScene($scene);

        $this->recordManualSave($scene, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['scenes.edit', $scene], ['projects.scenes.index', $project]);
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
        $this->reorderSibling($scene, $scene->chapter->act->project, up: true);

        return $this->reorderResponse($request, $scene);
    }

    public function moveDown(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        $this->reorderSibling($scene, $scene->chapter->act->project, up: false);

        return $this->reorderResponse($request, $scene);
    }

    /**
     * Scenes are the one reorderable entity the Story overview moves over AJAX, so
     * their move actions answer JSON with the new position; every other caller is a
     * plain form post that redirects back (see the ReordersSiblings docblock).
     */
    private function reorderResponse(Request $request, Scene $scene): RedirectResponse|JsonResponse
    {
        return $request->wantsJson()
            ? response()->json(['position' => $scene->position])
            : redirect()->back();
    }

    private function chaptersFor(Project $project): Collection
    {
        return $project->chapterQuery()
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
