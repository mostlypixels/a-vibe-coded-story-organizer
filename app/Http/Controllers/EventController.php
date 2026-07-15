<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Project;
use App\Services\CodexAsOfResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $sort = in_array($request->query('sort'), ['title', 'event_datetime']) ? $request->query('sort') : 'event_datetime';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

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

        [$windowMin, $windowMax] = $this->datetimeBounds($project, null);

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

    public function edit(Event $event, CodexAsOfResolver $codexAsOf): View
    {
        $this->authorize('update', $event->project);

        $event->load('plotlines', 'scenes', 'mentioningScenes');

        [$windowMin, $windowMax] = $this->datetimeBounds($event->project, $event);

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
        $event->update($request->safe()->except('plotlines'));

        $event->plotlines()->sync($request->validated('plotlines'));

        return $request->boolean('stay')
            ? redirect()->route('events.edit', $event)->with('status', 'saved')
            : redirect()->route('projects.events.index', $event->project);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('update', $event->project);

        abort_if($event->is_fixed, 403);

        $project = $event->project;
        $event->delete();

        return redirect()->route('projects.events.index', $project);
    }

    /**
     * datetime-local min/max hints for the event's editable window — the UI mirror of
     * WithinEventWindow. A regular event (or a new one, $event === null) sits inside
     * [Start, End]; a bookend is instead capped by the nearest regular event, falling
     * back to the opposite bookend when the project holds only its two bookends. The
     * server rule stays authoritative; these attributes are only a browser hint.
     *
     * @return array{0: ?string, 1: ?string} [min, max], each 'Y-m-d\TH:i' or null
     */
    private function datetimeBounds(Project $project, ?Event $event): array
    {
        $format = fn (?Event $bound) => $bound?->event_datetime->format('Y-m-d\TH:i');
        $start = $project->startEvent();
        $end = $project->endEvent();

        if ($event?->is_fixed && $event->is($start)) {
            return [null, $format($project->earliestRegularEvent() ?? $end)];
        }

        if ($event?->is_fixed && $event->is($end)) {
            return [$format($project->latestRegularEvent() ?? $start), null];
        }

        return [$format($start), $format($end)];
    }
}
