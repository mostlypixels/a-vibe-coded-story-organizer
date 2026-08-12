<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ProjectDeleteWarning;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // The counts feed each row's delete warning. One aggregate query for the whole
        // list, not one per row.
        $projects = $request->user()->projects()
            ->withCount(ProjectDeleteWarning::countRelations())
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'projects' => $projects,
            // One warning per row, built here so the Blade stays free of domain logic.
            'deleteConfirms' => $projects->mapWithKeys(
                fn (Project $project) => [$project->id => ProjectDeleteWarning::for($project)]
            ),
        ]);
    }
}
