<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class TimelineController extends Controller
{
    /**
     * The Timeline section landing page. Placeholder stub — a real section
     * dashboard lands here later (same shape as StoryController::home).
     */
    public function home(Project $project): View
    {
        $this->authorize('view', $project);

        return view('timeline.home', ['project' => $project]);
    }
}
