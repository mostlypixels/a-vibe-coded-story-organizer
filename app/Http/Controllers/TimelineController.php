<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\RecentlyEdited;
use Illuminate\View\View;

class TimelineController extends Controller
{
    /**
     * The Timeline section landing page: the plotlines and events touched most
     * recently (same shape as StoryController::home).
     */
    public function home(Project $project, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $project);

        return view('timeline.home', [
            'project' => $project,
            'recentPlotlines' => $recentlyEdited->plotlines($project),
            'recentEvents' => $recentlyEdited->events($project),
        ]);
    }
}
