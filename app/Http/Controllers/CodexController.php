<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

/**
 * The Codex *section* landing page — distinct from {@see CodexEntryController},
 * which handles individual codex entries (characters, locations, organizations).
 */
class CodexController extends Controller
{
    /**
     * Placeholder stub — a real section dashboard lands here later (same shape
     * as StoryController::home).
     */
    public function home(Project $project): View
    {
        $this->authorize('view', $project);

        return view('codex.home', ['project' => $project]);
    }
}
