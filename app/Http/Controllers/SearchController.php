<?php

namespace App\Http\Controllers;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Http\Requests\SearchRequest;
use App\Models\Project;
use App\Services\ProjectSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

        // Default to AllTerms (match all words) when no mode is submitted.
        // Validation guarantees any present value is a valid SearchMode by the
        // time we get here.
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

    /**
     * One domain's full, paginated result set — the "see all N results"
     * destination linked from the capped main-page column.
     *
     * The page has no search box of its own, so a blank `q` has nothing to
     * paginate: redirect back to the main search page instead of rendering an
     * empty table.
     */
    public function domain(SearchRequest $request, Project $project, SearchDomain $domain): View|RedirectResponse
    {
        $this->authorize('view', $project);

        $query = $request->validated('q');
        $mode = $request->enum('mode', SearchMode::class) ?? SearchMode::AllTerms;

        if (blank($query)) {
            return redirect()->route('projects.search.index', [
                'project' => $project,
                'mode' => $mode,
            ]);
        }

        $matches = app(ProjectSearch::class)->searchDomain($project, $domain, $query, $mode);

        $page = max(1, $request->integer('page', 1));
        $perPage = config('search.per_page');

        // PHP-side slice of the already-matched collection — never a SQL
        // LIMIT/OFFSET, which would page over fetched rows before PHP matching
        // ran (see ProjectSearch::searchDomain).
        $paginator = new LengthAwarePaginator(
            $matches->forPage($page, $perPage)->values(),
            $matches->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
        $paginator->appends($request->only('q', 'mode'));

        return view('search.domain', [
            'project' => $project,
            'domain' => $domain,
            'query' => $query,
            'mode' => $mode,
            'paginator' => $paginator,
        ]);
    }
}
