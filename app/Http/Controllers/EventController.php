<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Project;
use App\Services\CodexAsOfResolver;
use App\Services\EventLifespanEntries;
use App\Support\EventWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ResolvesIndexSorting;

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        [$sort, $direction] = $this->resolveSorting($request, ['title', 'event_datetime'], 'event_datetime');

        $events = $project->events()
            ->with('plotlines')
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('plotline'), fn ($query) => $query->whereHas(
                'plotlines',
                fn ($plotlineQuery) => $plotlineQuery->where('plotlines.id', $request->query('plotline'))
            ))
            ->orderBy($sort, $direction)
            ->get();

        return view('events.index', [
            'project' => $project->load('plotlines'),
            'events' => $events,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        [$windowMin, $windowMax] = EventWindow::forRegularEvent($project);

        return view('events.create', [
            'project' => $project->load('plotlines'),
            'windowMin' => $windowMin,
            'windowMax' => $windowMax,
        ]);
    }

    public function store(StoreEventRequest $request, Project $project): RedirectResponse
    {
        $event = $project->events()->create($request->safe()->except('plotlines'));

        $event->plotlines()->sync($request->validated('plotlines'));

        return redirect()->route('projects.events.index', $project);
    }

    public function show(Event $event, EventLifespanEntries $lifespanEntries): View
    {
        $this->authorize('view', $event->project);

        $event->load('plotlines', 'scenes.chapter.act', 'mentioningScenes.chapter.act');

        // Scenes come back in insertion order; readers expect manuscript order.
        $byManuscriptOrder = fn ($scenes) => $scenes->sortBy(fn ($scene) => [
            $scene->chapter->act->position,
            $scene->chapter->position,
            $scene->position,
        ])->values();

        return view('events.show', [
            'event' => $event,
            'scenesOnEvent' => $byManuscriptOrder($event->scenes),
            'mentioningScenes' => $byManuscriptOrder($event->mentioningScenes),
            'lifespanEntries' => $lifespanEntries->forEvent($event),
        ]);
    }

    public function edit(Event $event, CodexAsOfResolver $codexAsOf): View
    {
        $this->authorize('update', $event->project);

        $event->load('plotlines', 'scenes', 'mentioningScenes');

        [$windowMin, $windowMax] = EventWindow::forEvent($event->project, $event);

        return view('events.edit', [
            'event' => $event,
            'project' => $event->project->load('plotlines'),
            'windowMin' => $windowMin,
            'windowMax' => $windowMax,
            // Codex values resolved as of this event. The moment is the event itself, so the
            // anchor-identity rule applies (its own anchored values win over datetime ties).
            // Pre-computed here to keep resolution out of Blade and avoid N+1 across entries.
            'codexAsOfGroups' => $codexAsOf->resolve($event->project, $event),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->safe()->except('plotlines');
        $beforeAutosavedFields = $this->snapshotAutosaved($event, $data);

        $event->update($data);

        $event->plotlines()->sync($request->validated('plotlines'));

        $this->recordManualSave($event, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['events.edit', $event], ['projects.events.index', $event->project]);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('update', $event->project);

        abort_if($event->is_fixed, 403);

        $project = $event->project;
        $event->delete();

        return redirect()->route('projects.events.index', $project);
    }
}
