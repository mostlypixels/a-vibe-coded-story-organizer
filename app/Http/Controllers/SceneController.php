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
use App\Support\StoryNumbering;
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
            // Only the scene columns: the two joins below are there to sort by, not to
            // select from, and without this the joined `chapters`/`acts` columns would
            // overwrite same-named scene attributes on the hydrated models. Safe here
            // because this query has no withCount/withSum whose aliases a select() would
            // reset (the chapters index does — see ChapterController::index).
            ->select('scenes.*')
            // Joined so the `#` column can sort by story order: act order, then chapter
            // within the act, then scene within the chapter. Grouping by `chapter_id`
            // instead — as this did — only matches story order until something is
            // reordered. `chapters` and `acts` both carry `name` and `position`, so every
            // column below is table-qualified to stay unambiguous.
            ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
            ->join('acts', 'acts.id', '=', 'chapters.act_id')
            ->with('chapter.act', 'event')
            ->when($request->filled('search'), fn ($query) => $query->where('scenes.name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('chapter'), fn ($query) => $query->where('scenes.chapter_id', $request->query('chapter')))
            ->when(
                $sort === 'position',
                // $direction is applied to every key, so descending reads as the story
                // backwards rather than acts ascending with scenes reversed inside them.
                // The id tie-breaks are part of the contract: `position` has no unique
                // constraint, so two siblings can share one and must still order stably.
                fn ($query) => $query
                    ->orderBy('acts.position', $direction)
                    ->orderBy('acts.id', $direction)
                    ->orderBy('chapters.position', $direction)
                    ->orderBy('chapters.id', $direction)
                    ->orderBy('scenes.position', $direction)
                    ->orderBy('scenes.id', $direction),
                // $sort is allow-listed by resolveSorting(), so it is safe to qualify.
                fn ($query) => $query->orderBy('scenes.'.$sort, $direction)
            )
            ->get();

        return view('scenes.index', [
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
            'scenes' => $scenes,
            'sort' => $sort,
            'direction' => $direction,
            // Built from the whole project, never the filtered/paginated $scenes
            // above — a scenes list filtered to one chapter must still start
            // counting from that chapter's true project-wide number.
            'numbering' => StoryNumbering::forProject($project),
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

        // This scene's rank among its chapter's siblings, for the "2 of 5" half of
        // the position hint — a gap-free rank, not the raw (possibly gappy)
        // `position` column. Same (position, id) tie-break as StoryNumbering, one
        // level deep.
        $siblingIds = $scene->chapter->scenes()->orderBy('position')->orderBy('id')->pluck('id');

        return view('scenes.edit', [
            'scene' => $scene,
            'project' => $project,
            'chapters' => $this->chaptersFor($project),
            'events' => $this->eventsFor($project),
            'windowMin' => $project->startEvent()->event_datetime->format('Y-m-d\TH:i'),
            'windowMax' => $project->endEvent()->event_datetime->format('Y-m-d\TH:i'),
            'numbering' => StoryNumbering::forProject($project),
            'positionInChapter' => $siblingIds->search($scene->id) + 1,
            'totalInChapter' => $siblingIds->count(),
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
