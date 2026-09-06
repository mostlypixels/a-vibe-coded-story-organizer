<?php

namespace App\Support;

use App\Enums\CodexEntryType;
use App\Models\Book;
use App\Models\Project;
use ArrayIterator;
use Countable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IteratorAggregate;
use Traversable;

/**
 * The breadcrumb trail for the current request, rooted at the project
 * Dashboard and mirroring the primary nav's section structure
 * (`project-menu.blade.php`).
 *
 * Built off `$navigation->routeProject`/`routeBook`, never `->project`/`->book`
 * — the same rule as {@see PageTitle}, for the same reason: off-route pages
 * (dashboard, `/profile`, `/admin/*`) must not inherit the account's stored
 * active project or book, they must show no trail at all so the layout falls
 * back to the page's own `header` slot. Reuses `ProjectNavigation`'s
 * `*Active` flags rather than re-deriving "which section is active" — that
 * decision lives in exactly one place. A book-scoped page also gets a book
 * crumb (see `bookCrumb()`), present only when the book has a name of its own.
 *
 * Fully central: every label comes from the route name or a route-bound
 * model, never a view. The one documented exception is the revisions
 * history/compare pages (`revisions.index` etc.), which bind `{entity}`+`{id}`
 * rather than a `{project}` model — `RouteContext::resolve` yields null for
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

        // The book home is not a section like Story/Timeline/Codex/Tools — it
        // IS the book crumb, as the current leaf. An unnamed book has no
        // crumb to show (see bookCrumb()), so it renders no band at all and
        // the page falls back to its own header slot, like every other
        // unmatched route below.
        if ($request->routeIs('books.show')) {
            $bookCrumb = $this->bookCrumb($navigation->routeBook, current: true);

            return $bookCrumb === []
                ? []
                : [new Crumb(__('Dashboard'), route('projects.show', $project)), ...$bookCrumb];
        }

        $section = match (true) {
            $navigation->searchActive => [new Crumb(__('Search'), current: true)],
            $navigation->storyActive => $this->storyTrail($navigation->routeBook, $request),
            $navigation->timelineActive => $this->timelineTrail($project, $request),
            $navigation->codexActive => $this->codexTrail($navigation, $project, $request),
            $navigation->toolsActive => $this->toolsTrail($project, $request),
            default => [],
        };

        // A section that cannot name the page produces no trail at all, not a
        // lone Dashboard crumb. A trail is either empty or it ends in the
        // current page, and the layout falls back to the page's own `header`
        // slot for the empty case.
        if ($section === []) {
            return [];
        }

        return [new Crumb(__('Dashboard'), route('projects.show', $project)), ...$section];
    }

    /**
     * The book crumb: present only when the book has a name of its own (see
     * {@see Book::hasOwnName()}) — a sole unnamed book renders no
     * crumb, exactly today's trail. Links `books.show` unless it is itself
     * the current leaf.
     *
     * @return list<Crumb>
     */
    private function bookCrumb(Book $book, bool $current): array
    {
        if (! $book->hasOwnName()) {
            return [];
        }

        return [new Crumb($book->displayName(), $current ? null : route('books.show', $book), $current)];
    }

    /**
     * @return list<Crumb>
     */
    private function storyTrail(Book $book, Request $request): array
    {
        $bookCrumb = $this->bookCrumb($book, current: false);

        // On the section stub itself, Story is the current leaf.
        if ($request->routeIs('books.story.home')) {
            return [...$bookCrumb, new Crumb(__('Story'), current: true)];
        }

        // Everywhere below, Story links back to its stub landing.
        $section = new Crumb(__('Story'), route('books.story.home', $book));

        // Story Overview has no create/edit page of its own — it is always
        // its own current leaf, like any other *.index route below.
        if ($request->routeIs('books.story.overview')) {
            return [...$bookCrumb, $section, new Crumb(__('Overview'), current: true)];
        }

        if ($request->routeIs('books.acts.*', 'acts.*')) {
            return [...$bookCrumb, $section, ...$this->entityTrail(
                $book, $request, __('Acts'), 'books.acts.index', 'books.acts.create', 'acts.edit', 'acts.show', 'act', __('act')
            )];
        }

        if ($request->routeIs('books.chapters.*', 'chapters.*')) {
            return [...$bookCrumb, $section, ...$this->entityTrail(
                $book, $request, __('Chapters'), 'books.chapters.index', 'books.chapters.create', 'chapters.edit', 'chapters.show', 'chapter', __('chapter')
            )];
        }

        return [...$bookCrumb, $section, ...$this->entityTrail(
            $book, $request, __('Scenes'), 'books.scenes.index', 'books.scenes.create', 'scenes.edit', 'scenes.show', 'scene', __('scene')
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
                $project, $request, __('Plotlines'), 'projects.plotlines.index', 'projects.plotlines.create', 'plotlines.edit', 'plotlines.show', 'plotline', __('plotline')
            )];
        }

        return [$section, ...$this->entityTrail(
            $project, $request, __('Events'), 'projects.events.index', 'projects.events.create', 'events.edit', 'events.show', 'event', __('event')
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
                $project, $request, __('Attributes'), 'projects.codex-attributes.index', 'projects.codex-attributes.create', 'codex-attributes.edit', null, 'codexAttribute', __('attribute')
            )];
        }

        // Tags manage on one page, so the trail stops at the section.
        if ($navigation->tagsActive) {
            return [$section, new Crumb(__('Tags'), current: true)];
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

        if ($request->routeIs('codex.show')) {
            return [
                $section,
                new Crumb($type->pluralLabel(), $indexUrl),
                $this->readCrumb($request->route('codexEntry')),
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

        if ($request->routeIs('projects.revisions.*')) {
            return [
                new Crumb(__('Tools'), route('projects.tools.home', $project)),
                new Crumb(__('Revisions'), current: true),
            ];
        }

        if ($request->routeIs('projects.progress')) {
            return [
                new Crumb(__('Tools'), route('projects.tools.home', $project)),
                new Crumb(__('Progress'), current: true),
            ];
        }

        // > [!WARNING]
        // > A new tool needs its own branch above. Each sibling trail names the
        // > page it labels; this one must too. An unmatched Tools route gets an
        // > empty trail, so the layout falls back to the page's own `header`
        // > slot — the same result as build()'s `default`. A missing trail is
        // > visible. A trail that says "Revisions" on a page about something
        // > else is not.
        return [];
    }

    /**
     * The leaf of a read page: the saved name, plus the id in parentheses.
     *
     * Two entities can share a name, so the name alone does not identify the
     * page. The id is parenthesised and hashed rather than leading, because a
     * leading number reads as the story number the act/chapter/scene headings
     * show, and the two are not the same value.
     */
    private function readCrumb(Model $model): Crumb
    {
        return new Crumb(
            __(':name (#:id)', ['name' => $model->revisionDisplayName(), 'id' => $model->getKey()]),
            current: true,
        );
    }

    /**
     * The Section → sub-index(linked) → leaf pattern shared by every project
     * entity that follows the index/create/edit convention. The *.index
     * route IS the current leaf — no duplicate crumb — while create/edit
     * append an action-precise leaf that names the operation: "New <thing>" /
     * "Edit <thing> <id>". The id is the bound model's primary key, which
     * matches the URL — not the model's name, which the form can change under
     * the trail. A read page names the entity instead (see {@see readCrumb()}).
     *
     * @param  Project|Book  $parent  Whatever the index route nests under.
     * @param  string  $thing  Lowercase singular, mid-sentence after the verb
     *                         (e.g. "chapter" in "Edit chapter 1").
     * @return list<Crumb>
     */
    private function entityTrail(
        Project|Book $parent,
        Request $request,
        string $indexLabel,
        string $indexRoute,
        string $createRoute,
        string $editRoute,
        ?string $showRoute,
        string $routeParam,
        string $thing,
    ): array {
        if ($request->routeIs($createRoute)) {
            return [
                new Crumb($indexLabel, route($indexRoute, $parent)),
                new Crumb(__('New :thing', ['thing' => $thing]), current: true),
            ];
        }

        if ($showRoute !== null && $request->routeIs($showRoute)) {
            return [
                new Crumb($indexLabel, route($indexRoute, $parent)),
                $this->readCrumb($request->route($routeParam)),
            ];
        }

        if ($request->routeIs($editRoute)) {
            $model = $request->route($routeParam);

            return [
                new Crumb($indexLabel, route($indexRoute, $parent)),
                new Crumb(__('Edit :thing :id', ['thing' => $thing, 'id' => $model->id]), current: true),
            ];
        }

        return [new Crumb($indexLabel, current: true)];
    }
}
