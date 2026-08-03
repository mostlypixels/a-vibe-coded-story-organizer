<?php

namespace App\Support;

use App\Enums\CodexEntryType;
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
 * add the route-parameter fallback to RouteProject::resolve() if the section
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

    public readonly bool $homeActive;

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

    /** Tools dropdown — the Revisions browser, for now. */
    public readonly bool $toolsActive;

    /** The codex type being viewed, if any. Read via codexTypeIsActive(). */
    private readonly ?CodexEntryType $activeCodexType;

    /** Signed-in user, for the picker's project list. Null on guest renders. */
    private readonly ?User $user;

    /** Memoized otherProjects() result — both menus ask for it. */
    private ?Collection $otherProjects = null;

    public function __construct(Request $request)
    {
        $this->routeProject = RouteProject::resolve($request);
        $this->user = $request->user();
        $this->project = $this->routeProject ?? $this->user?->activeProject;

        $this->homeActive = $request->routeIs('projects.show');

        // Each section matches both its project-scoped index routes
        // (projects.acts.*) and its shallow child routes (acts.edit), so a page
        // reached by either keeps its section highlighted.
        $this->storyOverviewActive = $request->routeIs('projects.story.*');
        $this->actsActive = $request->routeIs('projects.acts.*', 'acts.*');
        $this->chaptersActive = $request->routeIs('projects.chapters.*', 'chapters.*');
        $this->scenesActive = $request->routeIs('projects.scenes.*', 'scenes.*');
        $this->storyActive = $this->storyOverviewActive
            || $this->actsActive
            || $this->chaptersActive
            || $this->scenesActive;

        $this->plotlinesActive = $request->routeIs('projects.plotlines.*', 'plotlines.*');
        $this->eventsActive = $request->routeIs('projects.events.*', 'events.*');
        $this->timelineActive = $this->plotlinesActive || $this->eventsActive;

        // Attributes and the codex types are distinct namespaces: an attribute
        // page highlights Codex but no individual type.
        $this->attributesActive = $request->routeIs('projects.codex-attributes.*', 'codex-attributes.*');
        $this->activeCodexType = $this->resolveActiveCodexType($request);
        $this->codexActive = $request->routeIs('projects.codex.*', 'codex.*') || $this->attributesActive;

        $this->searchActive = $request->routeIs('projects.search.*');

        // The per-field history/compare routes aren't project-scoped, so match
        // those too — the menu stays highlighted while browsing a field's history.
        $this->toolsActive = $request->routeIs('projects.revisions.*', 'revisions.*');
    }

    /** Whether there is a project to build project-scoped links from. */
    public function hasProject(): bool
    {
        return $this->project !== null;
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
     * authenticated page.
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
