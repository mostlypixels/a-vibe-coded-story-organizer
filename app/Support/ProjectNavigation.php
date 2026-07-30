<?php

namespace App\Support;

use App\Enums\CodexEntryType;
use App\Models\CodexEntry;
use App\Models\Project;
use Illuminate\Http\Request;

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
 * add the route-parameter fallback to resolveProject() if the section owns
 * models of its own, and add one `*Active` property below.
 */
class ProjectNavigation
{
    /** The project the current page belongs to, or null outside a project. */
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

    public function __construct(Request $request)
    {
        $this->project = $this->resolveProject($request);

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

    /** Whether the page currently shown belongs to this codex type. */
    public function codexTypeIsActive(CodexEntryType $type): bool
    {
        return $this->activeCodexType === $type;
    }

    /**
     * Walk whatever model the route bound back up to its project.
     *
     * Shallow child routes (e.g. /scenes/{scene}/edit) carry no {project}
     * parameter, so the nav has to climb the aggregate to find one.
     */
    private function resolveProject(Request $request): ?Project
    {
        return $request->route('project')
            ?? $request->route('plotline')?->project
            ?? $request->route('event')?->project
            ?? $request->route('act')?->project
            ?? $request->route('chapter')?->act?->project
            ?? $request->route('scene')?->chapter?->act?->project
            ?? $request->route('codexEntry')?->project
            ?? $request->route('codexAttribute')?->project;
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
