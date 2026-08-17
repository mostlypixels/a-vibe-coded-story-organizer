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
 * View model for the primary navigation.
 *
 * Answers the only two questions the nav asks of the request: *which project
 * are we in?* and *which section is active?* Both used to live as inline @php
 * in layouts/navigation.blade.php, duplicated between the desktop and the
 * responsive menu — so a flag added to one copy silently went missing from the
 * other. One object, computed once per request by the view composer in
 * AppServiceProvider, is the single source of truth for both menus.
 *
 * Adding a project-scoped section means touching this class and nothing else:
 * add the route-parameter fallback to RouteContext::resolve() if the section
 * owns models of its own, and add one `*Active` property below.
 *
 * The two questions have different answers since active-project persistence:
 * $project falls back to the account's stored project off-route, while every
 * `*Active` flag still matches the route alone. The dashboard therefore renders
 * the project menu with nothing highlighted — correct, no section is open.
 */
class ProjectNavigation
{
    /** How many projects the picker offers before deferring to "All projects". */
    private const PICKER_PROJECT_LIMIT = 5;

    /** How many of another project's books the picker offers, per project. */
    private const PICKER_BOOK_LIMIT = 5;

    /**
     * The project the current *route* belongs to, or null outside a project.
     *
     * Distinct from $project on purpose: this one is null on the dashboard,
     * /profile and /admin/*, and it is what the <title> is built from. See the
     * note on $project.
     */
    public readonly ?Project $routeProject;

    /**
     * The project the nav works on: the route's project, or the account's stored
     * active project on a page with none.
     *
     * The route always wins when there is one — the stored value is a fallback,
     * never an override — so inside a project this is exactly $routeProject.
     * Outside one it is what keeps the project menu and picker on screen over
     * the dashboard and Configuration, so a settings detour costs one click to
     * return from.
     */
    public readonly ?Project $project;

    /**
     * The book the current *route* belongs to, or null when the route names
     * no book (the timeline, the codex, the tools section, or any page
     * outside a project). Never falls back — {@see PageTitle} needs to tell
     * "this page is in a book" from "the nav is showing the last book you
     * were in", the same distinction {@see $routeProject} draws for $project.
     */
    public readonly ?Book $routeBook;

    /**
     * The book the Story links point at: the route's book, or the project's
     * remembered book ({@see Project::lastBook()}), or its first
     * book when neither is set — so a Codex or Tools detour returns to the
     * book the writer was working in, not always to book one.
     *
     * Null on a guest or error render, where there is no project either — so
     * every `route('books.…', $navigation->book)` in the menus must guard on
     * {@see hasBook()}, not on {@see hasProject()}.
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

        // Each Story section matches both its book-scoped index routes
        // (books.acts.*) and its shallow child routes (acts.edit), so a page
        // reached by either keeps its section highlighted.
        // Overview highlights only its own route now that the Story section has
        // a separate stub landing (books.story.home); a `books.story.*` match
        // would light Overview up on the stub too.
        $this->storyOverviewActive = $request->routeIs('books.story.overview');
        $this->actsActive = $request->routeIs('books.acts.*', 'acts.*');
        $this->chaptersActive = $request->routeIs('books.chapters.*', 'chapters.*');
        $this->scenesActive = $request->routeIs('books.scenes.*', 'scenes.*');
        // `books.story.*` covers the stub (home) and Overview; the child
        // routes carry Story-section highlighting on their shallow edit pages.
        $this->storyActive = $request->routeIs('books.story.*')
            || $this->actsActive
            || $this->chaptersActive
            || $this->scenesActive;

        $this->plotlinesActive = $request->routeIs('projects.plotlines.*', 'plotlines.*');
        $this->eventsActive = $request->routeIs('projects.events.*', 'events.*');
        $this->timelineActive = $request->routeIs('projects.timeline.*')
            || $this->plotlinesActive
            || $this->eventsActive;

        // Attributes and the codex types are distinct namespaces: an attribute
        // page highlights Codex but no individual type.
        $this->attributesActive = $request->routeIs('projects.codex-attributes.*', 'codex-attributes.*');
        $this->activeCodexType = $this->resolveActiveCodexType($request);
        // `projects.codex.*` covers the section stub (home) and the per-type
        // index/create; the stub carries no {type}, so no individual type lights.
        $this->codexActive = $request->routeIs('projects.codex.*', 'codex.*') || $this->attributesActive;

        $this->searchActive = $request->routeIs('projects.search.*');

        // Revisions browser + the per-field history/compare routes (the latter
        // aren't project-scoped). The section stub plus these keep Tools lit.
        $this->revisionsActive = $request->routeIs('projects.revisions.*', 'revisions.*');
        $this->progressActive = $request->routeIs('projects.progress');
        $this->toolsActive = $request->routeIs('projects.tools.*') || $this->revisionsActive || $this->progressActive;

        // `projects.books.*` is safe as a wildcard (nothing else shares that
        // prefix); the shallow routes need naming individually, because
        // `books.*` would also sweep up every books.story.*/acts.*-style
        // manuscript route.
        $this->booksActive = $request->routeIs(
            'projects.books.*', 'books.edit', 'books.update', 'books.destroy', 'books.move-up', 'books.move-down'
        );
    }

    /**
     * A navigation that ignores the current route, for the error pages.
     *
     * The error pages must not read the route: a 403 route names a project the
     * viewer has no access to, and the picker would then show its name. This
     * builds the nav from the account alone, so the trigger shows the stored
     * active project and no section is highlighted.
     */
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

    /** Whether there is a book to build book-scoped links from. */
    public function hasBook(): bool
    {
        return $this->book !== null;
    }

    /**
     * The open project's own books, in reading order, for the picker's own
     * group — every one of them, the current project's book list is never
     * capped.
     *
     * Chaperoned: without it, {@see Book::displayName()} re-queries
     * the project for every unnamed book in the list (the common case), one
     * query each. Memoized for the same reason {@see otherProjects()} is —
     * the desktop panel and the responsive menu both render this list.
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
     * The user's other projects, for the picker's "switch to" list.
     *
     * Capped at five and ordered by name: the picker is a shortcut, not an
     * index. An uncapped list would grow past the fold for a prolific writer,
     * and the "All projects" link at the bottom of the panel is the complete,
     * paginated answer — so truncating here costs nothing but a click. Name
     * order (not id) because the list is read, not iterated: the same project
     * sits in the same place every time.
     *
     * Lazy and memoized: the desktop and responsive menus both render the
     * picker, and without the cache that is two identical queries on every
     * authenticated page. Each project's own books come eager-loaded and
     * capped at {@see PICKER_BOOK_LIMIT} for the same reason, chaperoned so
     * an unnamed one's {@see Book::displayName()} does not
     * re-query its project.
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
