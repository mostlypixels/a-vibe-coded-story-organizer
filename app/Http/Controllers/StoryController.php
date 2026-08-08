<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\RecentlyEdited;
use App\Support\StoryNumbering;
use Illuminate\View\View;

class StoryController extends Controller
{
    /**
     * The Story section landing page: the acts, chapters and scenes touched
     * most recently, each with a link to its full index. Same shape as the
     * Timeline and Codex home actions.
     */
    public function home(Project $project, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $project);

        return view('story.home', [
            'project' => $project,
            'recentActs' => $recentlyEdited->acts($project),
            'recentChapters' => $recentlyEdited->chapters($project),
            'recentScenes' => $recentlyEdited->scenes($project),
        ]);
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $acts = $project->acts()
            ->with('chapters.scenes.event')
            ->orderBy('position')
            ->get()
            ->each(function ($act) {
                $act->chapters = $act->chapters->sortBy('position')->each(function ($chapter) {
                    $chapter->scenes = $chapter->scenes->sortBy('position');
                });
            });

        // Scenes are already eager-loaded above, so this sums an in-memory
        // collection — no query fires. Per-act/chapter totals are the same
        // ->sum() over the same loaded relations, computed where they're
        // used in the view. There is no `wordCount()` accessor: an explicit sum
        // keeps it visible that this is free only because the data is already
        // in memory.
        $wordCount = $acts->sum(
            fn ($act) => $act->chapters->sum(
                fn ($chapter) => $chapter->scenes->sum('word_count'),
            ),
        );

        return view('story.index', [
            'project' => $project,
            'acts' => $acts,
            'wordCount' => $wordCount,
            // The tree is already fully eager-loaded above, so fromActs()
            // derives the numbering with zero extra queries.
            'numbering' => StoryNumbering::fromActs($acts),
        ]);
    }
}
