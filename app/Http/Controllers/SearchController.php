<?php

namespace App\Http\Controllers;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Http\Requests\SearchRequest;
use App\Models\Book;
use App\Models\Project;
use App\Services\ProjectSearch;
use App\Support\PageSize;
use App\Support\SearchScope;
use App\Support\SearchScopeFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
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
        $scope = SearchScopeFactory::fromRequest($request, $project);

        // blank() trims, so null, '' and whitespace-only queries all count as "no
        // search yet": render the form with no results and NO validation error.
        $results = blank($query)
            ? null
            : app(ProjectSearch::class)->search($project, $query, $mode, $scope);

        $books = $this->booksFor($project);
        $book = $this->resolvedBook($scope, $books);

        return view('search.index', [
            'project' => $project,
            'query' => $query,
            'mode' => $mode,
            'results' => $results,
            'scope' => $scope,
            'books' => $books,
            'book' => $book,
            // The whole picker exists only once a book is resolved (the chosen one,
            // or the project's only one) — a multi-book project with none chosen
            // runs no chapter query.
            'chapters' => $book?->chaptersInStoryOrder() ?? collect(),
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
        $scope = SearchScopeFactory::fromRequest($request, $project);

        if (blank($query)) {
            return redirect()->route('projects.search.index', [
                'project' => $project,
                'mode' => $mode,
            ] + $scope->toQuery());
        }

        // The URL names the domain; a stale domains[] checkbox from the main page
        // does not override it. Only a book that makes this domain meaningless
        // sends the reader back — there is nothing here for it to filter.
        if ($scope->hiddenByBook($domain)) {
            return redirect()->route('projects.search.index', [
                'project' => $project,
                'q' => $query,
                'mode' => $mode,
            ] + $scope->toQuery());
        }

        $matches = app(ProjectSearch::class)->searchDomain($project, $domain, $query, $mode, $scope);

        $page = max(1, $request->integer('page', 1));
        // The reader's own rows-per-page, the same one every entity list honours.
        // This page used to keep a separate `search.per_page`, which meant one screen
        // in ten quietly disagreed with the preference set on all the others.
        $perPage = PageSize::resolve($request->user()?->page_size);

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
        $paginator->appends($scope->toQuery() + $request->only('q', 'mode'));

        return view('search.domain', [
            'project' => $project,
            'domain' => $domain,
            'query' => $query,
            'mode' => $mode,
            'scope' => $scope,
            'paginator' => $paginator,
        ]);
    }

    /** @return Collection<int, Book> The project's books, ready for {@see Book::displayName()}. */
    private function booksFor(Project $project): Collection
    {
        return $project->books()->get()
            ->each(fn (Book $book) => $book->setRelation('project', $project));
    }

    /**
     * The book the chapter-range picker reads: the one the scope names, or the
     * project's only one. Numbering restarts per book, so the picker needs
     * exactly one to make sense of "chapter 3".
     *
     * @param  Collection<int, Book>  $books
     */
    private function resolvedBook(SearchScope $scope, Collection $books): ?Book
    {
        if ($scope->bookId !== null) {
            return $books->firstWhere('id', $scope->bookId);
        }

        return $books->count() === 1 ? $books->first() : null;
    }
}
