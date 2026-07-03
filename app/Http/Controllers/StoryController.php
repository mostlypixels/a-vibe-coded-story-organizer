<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class StoryController extends Controller
{
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

        return view('story.index', [
            'project' => $project,
            'acts' => $acts,
        ]);
    }
}
