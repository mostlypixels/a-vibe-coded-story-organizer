<?php

namespace App\Support;

use App\Enums\CodexEntryType;
use App\Models\Project;
use ArrayIterator;
use Countable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IteratorAggregate;
use Traversable;

/**
 * The breadcrumb trail for the current request, rooted at the project
 * Dashboard and mirroring the primary nav's section structure
 * (`project-menu.blade.php`).
 *
 * Built off `$navigation->routeProject`, never `->project` — the same rule
 * as {@see PageTitle}, for the same reason: off-route pages (dashboard,
 * `/profile`, `/admin/*`) must not inherit the account's stored active
 * project, they must show no trail at all so the layout falls back to the
 * page's own `header` slot. Reuses `ProjectNavigation`'s `*Active` flags
 * rather than re-deriving "which section is active" — that decision lives
 * in exactly one place.
 *
 * Fully central: every label comes from the route name or a route-bound
 * model, never a view. The one documented exception is the revisions
 * history/compare pages (`revisions.index` etc.), which bind `{entity}`+`{id}`
 * rather than a `{project}` model — `RouteProject::resolve` yields null for
 * them, so this class correctly produces an empty trail and those views
 * supply their own trail tail instead.
 *
 * @implements IteratorAggregate<int, Crumb>
 */
class Breadcrumbs implements Countable, IteratorAggregate
{
    /** @var list<Crumb> */
    private readonly array $crumbs;

    public function __construct(ProjectNavigation $navigation, Request $request)
    {
        $this->crumbs = $navigation->routeProject === null
            ? []
            : $this->build($navigation, $request);
    }

    public function isEmpty(): bool
    {
        return $this->crumbs === [];
    }

    public function count(): int
    {
        return count($this->crumbs);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->crumbs);
    }

    /**
     * @return list<Crumb>
     */
    private function build(ProjectNavigation $navigation, Request $request): array
    {
        $project = $navigation->routeProject;

        // Dashboard is the one page that renders a single, current crumb —
        // no ancestor to link back to.
        if ($navigation->homeActive) {
            return [new Crumb(__('Dashboard'), current: true)];
        }

        $dashboard = new Crumb(__('Dashboard'), route('projects.show', $project));

        return match (true) {
            $navigation->searchActive => [$dashboard, new Crumb(__('Search'), current: true)],
            $navigation->storyActive => [$dashboard, ...$this->storyTrail($project, $request)],
            $navigation->timelineActive => [$dashboard, ...$this->timelineTrail($project, $request)],
            $navigation->codexActive => [$dashboard, ...$this->codexTrail($navigation, $project, $request)],
            $navigation->toolsActive => [$dashboard, ...$this->toolsTrail($project, $request)],
            default => [],
        };
    }

    /**
     * @return list<Crumb>
     */
    private function storyTrail(Project $project, Request $request): array
    {
        // On the section stub itself, Story is the current leaf.
        if ($request->routeIs('projects.story.home')) {
            return [new Crumb(__('Story'), current: true)];
        }

        // Everywhere below, Story links back to its stub landing.
        $section = new Crumb(__('Story'), route('projects.story.home', $project));

        // Story Overview has no create/edit page of its own — it is always
        // its own current leaf, like any other *.index route below.
        if ($request->routeIs('projects.story.overview')) {
            return [$section, new Crumb(__('Overview'), current: true)];
        }

        if ($request->routeIs('projects.acts.*', 'acts.*')) {
            return [$section, ...$this->entityTrail(
                $project, $request, __('Acts'), 'projects.acts.index', 'projects.acts.create', 'acts.edit', 'act', __('act')
            )];
        }

        if ($request->routeIs('projects.chapters.*', 'chapters.*')) {
            return [$section, ...$this->entityTrail(
                $project, $request, __('Chapters'), 'projects.chapters.index', 'projects.chapters.create', 'chapters.edit', 'chapter', __('chapter')
            )];
        }

        return [$section, ...$this->entityTrail(
            $project, $request, __('Scenes'), 'projects.scenes.index', 'projects.scenes.create', 'scenes.edit', 'scene', __('scene')
        )];
    }

    /**
     * @return list<Crumb>
     */
    private function timelineTrail(Project $project, Request $request): array
    {
        if ($request->routeIs('projects.timeline.home')) {
            return [new Crumb(__('Timeline'), current: true)];
        }

        $section = new Crumb(__('Timeline'), route('projects.timeline.home', $project));

        if ($request->routeIs('projects.plotlines.*', 'plotlines.*')) {
            return [$section, ...$this->entityTrail(
                $project, $request, __('Plotlines'), 'projects.plotlines.index', 'projects.plotlines.create', 'plotlines.edit', 'plotline', __('plotline')
            )];
        }

        return [$section, ...$this->entityTrail(
            $project, $request, __('Events'), 'projects.events.index', 'projects.events.create', 'events.edit', 'event', __('event')
        )];
    }

    /**
     * @return list<Crumb>
     */
    private function codexTrail(ProjectNavigation $navigation, Project $project, Request $request): array
    {
        if ($request->routeIs('projects.codex.home')) {
            return [new Crumb(__('Codex'), current: true)];
        }

        $section = new Crumb(__('Codex'), route('projects.codex.home', $project));

        if ($navigation->attributesActive) {
            return [$section, ...$this->entityTrail(
                $project, $request, __('Attributes'), 'projects.codex-attributes.index', 'projects.codex-attributes.create', 'codex-attributes.edit', 'codexAttribute', __('attribute')
            )];
        }

        $type = $this->activeCodexType($navigation);

        // Not reachable in practice (codexActive implies a type or
        // attributes), but keeps this total rather than throwing.
        if ($type === null) {
            return [$section];
        }

        $indexUrl = route('projects.codex.index', [$project, $type->routeKey()]);

        if ($request->routeIs('projects.codex.create')) {
            return [
                $section,
                new Crumb($type->pluralLabel(), $indexUrl),
                new Crumb(__('New :thing', ['thing' => Str::lower($type->label())]), current: true),
            ];
        }

        if ($request->routeIs('codex.edit')) {
            $entry = $request->route('codexEntry');

            return [
                $section,
                new Crumb($type->pluralLabel(), $indexUrl),
                new Crumb(__('Edit :thing :id', ['thing' => Str::lower($type->label()), 'id' => $entry->id]), current: true),
            ];
        }

        // projects.codex.index (or any other codex.* route for this type):
        // the sub-index crumb IS the current leaf.
        return [$section, new Crumb($type->pluralLabel(), current: true)];
    }

    /** The codex type the current page is showing, via ProjectNavigation's own resolution. */
    private function activeCodexType(ProjectNavigation $navigation): ?CodexEntryType
    {
        foreach (CodexEntryType::cases() as $type) {
            if ($navigation->codexTypeIsActive($type)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<Crumb>
     */
    private function toolsTrail(Project $project, Request $request): array
    {
        // toolsActive also matches the per-field revisions.* routes, but
        // those have no {project} param, so routeProject is null and build()
        // never reaches here for them.
        if ($request->routeIs('projects.tools.home')) {
            return [new Crumb(__('Tools'), current: true)];
        }

        return [
            new Crumb(__('Tools'), route('projects.tools.home', $project)),
            new Crumb(__('Revisions'), current: true),
        ];
    }

    /**
     * The Section → sub-index(linked) → leaf pattern shared by every project
     * entity that follows the index/create/edit convention. The *.index
     * route IS the current leaf — no duplicate crumb — while create/edit
     * append an action-precise leaf that names the operation: "New <thing>" /
     * "Edit <thing> <id>". The id is the bound model's primary key, which
     * matches the URL — not the model's name.
     *
     * @param  string  $thing  Lowercase singular, mid-sentence after the verb
     *                         (e.g. "chapter" in "Edit chapter 1").
     * @return list<Crumb>
     */
    private function entityTrail(
        Project $project,
        Request $request,
        string $indexLabel,
        string $indexRoute,
        string $createRoute,
        string $editRoute,
        string $routeParam,
        string $thing,
    ): array {
        if ($request->routeIs($createRoute)) {
            return [
                new Crumb($indexLabel, route($indexRoute, $project)),
                new Crumb(__('New :thing', ['thing' => $thing]), current: true),
            ];
        }

        if ($request->routeIs($editRoute)) {
            $model = $request->route($routeParam);

            return [
                new Crumb($indexLabel, route($indexRoute, $project)),
                new Crumb(__('Edit :thing :id', ['thing' => $thing, 'id' => $model->id]), current: true),
            ];
        }

        return [new Crumb($indexLabel, current: true)];
    }
}
