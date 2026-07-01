<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventController extends Controller
{
    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('events.create', ['project' => $project->load('plotlines')]);
    }

    public function store(StoreEventRequest $request, Project $project): RedirectResponse
    {
        $event = $project->events()->create($request->safe()->except('plotlines'));

        $event->plotlines()->sync($request->validated('plotlines'));

        return redirect()->route('projects.show', $project);
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

        return redirect()->route('projects.show', $event->project);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('update', $event->project);

        $project = $event->project;
        $event->delete();

        return redirect()->route('projects.show', $project);
    }
}
