<?php

namespace App\Support;

use App\Enums\CodexEntryType;
use App\Models\Book;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Resolves the primary navigation context and active section.
 *
 * The navigation can use the stored project off-route. Active flags use only
 * the current route.
 */
class ProjectNavigation
{
    /** How many projects the picker offers before deferring to "All projects". */
    private const PICKER_PROJECT_LIMIT = 5;

    /** How many of another project's books the picker offers, per project. */
    private const PICKER_BOOK_LIMIT = 5;

    /** The route's project, or null outside a project route. */
    public readonly ?Project $routeProject;

    /** The route's project, with the account's active project as a fallback. */
    public readonly ?Project $project;

    /** The route's book, without a stored-context fallback. */
    public readonly ?Book $routeBook;

    /**
     * The route's book, then the project's last or first book.
     *
     * Book links must use {@see hasBook()} because guests have no fallback.
     */
    public readonly ?Book $book;

    public readonly bool $homeActive;

    /** Each true only on its section's stub landing (projects.<section>.home). */
    public readonly bool $storyHomeActive;

    public readonly bool $timelineHomeActive;

    public readonly bool $codexHomeActive;

    public readonly bool $toolsHomeActive;

    public readonly bool $storyOverviewActive;

    public readonly bool $actsActive;

    public readonly bool $chaptersActive;

    public readonly bool $scenesActive;

    /** True on any Story page — the dropdown trigger's active state. */
    public readonly bool $storyActive;

    public readonly bool $plotlinesActive;

    public readonly bool $eventsActive;

    public readonly bool $timelineActive;

    public readonly bool $attributesActive;

    public readonly bool $codexActive;

    public readonly bool $searchActive;

    /** The Revisions browser + per-field history routes (a Tools submenu item). */
    public readonly bool $revisionsActive;

    /** The Progress page (a Tools submenu item). */
    public readonly bool $progressActive;

    /** Tools dropdown trigger — true on the section stub or any Revisions/Progress page. */
    public readonly bool $toolsActive;

    /**
     * The books CRUD area (index/create/edit) — the picker's "Manage books"
     * link lights up here. Deliberately excludes `books.show`, the book home
     * page a book row in the picker links to, not the management screen.
     */
    public readonly bool $booksActive;

    /** The codex type being viewed, if any. Read via codexTypeIsActive(). */
    private readonly ?CodexEntryType $activeCodexType;

    /** Signed-in user, for the picker's project list. Null on guest renders. */
    private readonly ?User $user;

    /** Memoized otherProjects() result — both menus ask for it. */
    private ?Collection $otherProjects = null;

    /** Memoized projectBooks() result — both menus ask for it. */
    private ?Collection $projectBooks = null;

    public function __construct(Request $request)
    {
        $context = RouteContext::resolve($request);

        $this->routeProject = $context->project;
        $this->user = $request->user();
        $this->project = $this->routeProject ?? $this->user?->activeProject;
        $this->routeBook = $context->book;
        $this->book = $this->routeBook ?? $this->project?->lastBook ?? $this->project?->books()->first();

        $this->homeActive = $request->routeIs('projects.show');

        $this->storyHomeActive = $request->routeIs('books.story.home');
        $this->timelineHomeActive = $request->routeIs('projects.timeline.home');
        $this->codexHomeActive = $request->routeIs('projects.codex.home');
        $this->toolsHomeActive = $request->routeIs('projects.tools.home');

        // Match both book-scoped indexes and shallow child routes.
        $this->storyOverviewActive = $request->routeIs('books.story.overview');
        $this->actsActive = $request->routeIs('books.acts.*', 'acts.*');
        $this->chaptersActive = $request->routeIs('books.chapters.*', 'chapters.*');
        $this->scenesActive = $request->routeIs('books.scenes.*', 'scenes.*');
        $this->storyActive = $request->routeIs('books.story.*')
            || $this->actsActive
            || $this->chaptersActive
            || $this->scenesActive;

        $this->plotlinesActive = $request->routeIs('projects.plotlines.*', 'plotlines.*');
        $this->eventsActive = $request->routeIs('projects.events.*', 'events.*');
        $this->timelineActive = $request->routeIs('projects.timeline.*')
            || $this->plotlinesActive
            || $this->eventsActive;

        // Attribute pages activate Codex but no entry type.
        $this->attributesActive = $request->routeIs('projects.codex-attributes.*', 'codex-attributes.*');
        $this->activeCodexType = $this->resolveActiveCodexType($request);
        $this->codexActive = $request->routeIs('projects.codex.*', 'codex.*') || $this->attributesActive;

        $this->searchActive = $request->routeIs('projects.search.*');

        $this->revisionsActive = $request->routeIs('projects.revisions.*', 'revisions.*');
        $this->progressActive = $request->routeIs('projects.progress');
        $this->toolsActive = $request->routeIs('projects.tools.*') || $this->revisionsActive || $this->progressActive;

        // Do not use `books.*`; manuscript routes share that prefix.
        $this->booksActive = $request->routeIs(
            'projects.books.*', 'books.edit', 'books.update', 'books.destroy', 'books.move-up', 'books.move-down'
        );
    }

    /** Prevents an error page from exposing a project named by a forbidden route. */
    public static function offRoute(?User $user): self
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        return new self($request);
    }

    /** Whether there is a project to build project-scoped links from. */
    public function hasProject(): bool
    {
        return $this->project !== null;
    }

    /**
     * Where the site logo goes: the active project's dashboard, or the project
     * list. The list itself redirects an empty account to onboarding, so no
     * project count is queried here.
     */
    public function homeUrl(): string
    {
        return $this->project !== null
            ? route('projects.show', $this->project)
            : route('projects.index');
    }

    /** Whether there is a book to build book-scoped links from. */
    public function hasBook(): bool
    {
        return $this->book !== null;
    }

    /**
     * Memoizes all books in the current project and prevents display-name queries.
     *
     * @return Collection<int, Book>
     */
    public function projectBooks(): Collection
    {
        if ($this->project === null) {
            return collect();
        }

        return $this->projectBooks ??= $this->project->books()->chaperone('project')->get();
    }

    /**
     * Memoizes a short, named-ordered project list for both navigation menus.
     *
     * Books are eager-loaded to prevent display-name queries.
     *
     * @return Collection<int, Project>
     */
    public function otherProjects(): Collection
    {
        if ($this->user === null) {
            return collect();
        }

        return $this->otherProjects ??= $this->user->projects()
            ->when($this->project, fn ($query) => $query->whereKeyNot($this->project->getKey()))
            ->orderBy('name')
            ->limit(self::PICKER_PROJECT_LIMIT)
            ->with(['books' => fn ($query) => $query->orderBy('position')->limit(self::PICKER_BOOK_LIMIT)->chaperone('project')])
            ->get(['id', 'name']);
    }

    /** Whether the page currently shown belongs to this codex type. */
    public function codexTypeIsActive(CodexEntryType $type): bool
    {
        return $this->activeCodexType === $type;
    }

    /**
     * The codex type in play, from either the {type} route segment (index and
     * create pages) or the bound entry (edit pages). The two never co-occur.
     */
    private function resolveActiveCodexType(Request $request): ?CodexEntryType
    {
        $entry = $request->route('codexEntry');

        if ($entry instanceof CodexEntry) {
            return $entry->type;
        }

        $routeKey = $request->route('type');

        return is_string($routeKey) && in_array($routeKey, CodexEntryType::routeKeys(), true)
            ? CodexEntryType::fromRouteKey($routeKey)
            : null;
    }
}
