<?php

namespace App\Http\Controllers;

use App\Enums\SearchMode;
use App\Http\Requests\SearchRequest;
use App\Models\Project;
use App\Services\ProjectSearch;
use Illuminate\View\View;

/**
 * Single-action search page for one project (follows the StoryController shape —
 * there is nothing to create/update/delete).
 *
 * The HTTP layer is deliberately thin: authorize, read the validated query and
 * mode, and delegate the actual searching to {@see ProjectSearch}. A blank query
 * is the normal landing state, so it skips the service entirely and passes
 * `results = null` to the view.
 */
class SearchController extends Controller
{
    public function index(SearchRequest $request, Project $project): View
    {
        $this->authorize('view', $project);

        $query = $request->validated('q');

        // Default to AND (match all words) when no mode is submitted — the binding
        // "default mode = AllTerms" decision. Validation guarantees any present
        // value is a valid SearchMode by the time we get here.
        $mode = $request->enum('mode', SearchMode::class) ?? SearchMode::AllTerms;

        // blank() trims, so null, '' and whitespace-only queries all count as "no
        // search yet": render the form with no results and NO validation error.
        $results = blank($query)
            ? null
            : app(ProjectSearch::class)->search($project, $query, $mode);

        return view('search.index', [
            'project' => $project,
            'query' => $query,
            'mode' => $mode,
            'results' => $results,
        ]);
    }
}
