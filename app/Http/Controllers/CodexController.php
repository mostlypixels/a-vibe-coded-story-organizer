<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Models\Project;
use App\Services\RecentlyEdited;
use Illuminate\View\View;

/**
 * The Codex *section* landing page — distinct from {@see CodexEntryController},
 * which handles individual codex entries (characters, locations, organizations).
 */
class CodexController extends Controller
{
    /**
     * The most recently touched entries per type (same shape as
     * StoryController::home). Attribute definitions get no list: authors set
     * them up once and rarely touch them again, so they never answer "where
     * was I last working?".
     */
    public function home(Project $project, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $project);

        $recentEntries = [];

        foreach (CodexEntryType::cases() as $type) {
            $recentEntries[$type->value] = $recentlyEdited->codexEntries($project, $type);
        }

        return view('codex.home', [
            'project' => $project,
            'recentEntries' => $recentEntries,
        ]);
    }
}
