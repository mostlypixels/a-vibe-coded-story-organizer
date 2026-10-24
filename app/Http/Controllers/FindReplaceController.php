<?php

namespace App\Http\Controllers;

use App\Http\Requests\FindReplaceApplyRequest;
use App\Http\Requests\FindReplacePreviewRequest;
use App\Models\Project;
use App\Services\SceneFindReplace;
use App\Support\ScopePicker;
use App\Support\SearchScopeFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The find and replace page. It shows the form, and a preview of every match when
 * the author gave a find text. The preview is the first step of a write, so both
 * actions need `update` on the project.
 */
class FindReplaceController extends Controller
{
    public function index(FindReplacePreviewRequest $request, Project $project, SceneFindReplace $replacer): View
    {
        $this->authorize('update', $project);

        $query = $request->findReplaceQuery();
        $scope = SearchScopeFactory::fromRequest($request, $project);
        $picker = ScopePicker::for($project, $scope);

        return view('find-replace.index', [
            'project' => $project,
            'query' => $query,
            'scope' => $scope,
            'preview' => $query->isBlank() ? null : $replacer->preview($project, $query, $scope),
            'books' => $picker->books,
            'book' => $picker->book,
            'chapters' => $picker->chapters,
            'acts' => $picker->acts,
            'scopeSummary' => $picker->summary($scope),
            'maxMatches' => SceneFindReplace::MAX_MATCHES,
        ]);
    }

    public function store(FindReplaceApplyRequest $request, Project $project, SceneFindReplace $replacer): RedirectResponse
    {
        $this->authorize('update', $project);

        $query = $request->findReplaceQuery();
        $result = $replacer->apply($project, $query, $request->selection() ?? [], $request->user());

        // The preview after the redirect shows the matches that are left.
        return redirect()
            ->route('projects.replace.index', ['project' => $project] + $query->toQuery() + SearchScopeFactory::fromRequest($request, $project)->toQuery())
            ->with('replace_result', $result->toArray());
    }
}
