<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Project;
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

        return view('events.create', ['project' => $project->load('plotlines')]);
    }

    public function store(StoreEventRequest $request, Project $project): RedirectResponse
    {
        $event = $project->events()->create($request->safe()->except('plotlines'));

        $event->plotlines()->sync($request->validated('plotlines'));

        return redirect()->route('projects.events.index', $project);
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event->project);

        $event->load('plotlines');

        return view('events.edit', [
            'event' => $event,
            'project' => $event->project->load('plotlines'),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->safe()->except('plotlines'));

        $event->plotlines()->sync($request->validated('plotlines'));

        return redirect()->route('projects.events.index', $event->project);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('update', $event->project);

        $project = $event->project;
        $event->delete();

        return redirect()->route('projects.events.index', $project);
    }
}
