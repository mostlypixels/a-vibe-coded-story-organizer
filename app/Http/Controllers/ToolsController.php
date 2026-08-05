<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class ToolsController extends Controller
{
    /**
     * The Tools section landing page. Placeholder stub — a real section
     * dashboard lands here later (same shape as StoryController::home).
     */
    public function home(Project $project): View
    {
        $this->authorize('view', $project);

        return view('tools.home', ['project' => $project]);
    }
}
