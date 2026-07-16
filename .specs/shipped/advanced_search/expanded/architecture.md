# Architecture

## Route

```php
// routes/web.php, inside the existing auth group, alongside the other projects.* routes
Route::get('/projects/{project}/search', [SearchController::class, 'index'])->name('projects.search.index');
```

Add the nav entry last in both the desktop and responsive menus of
`resources/views/layouts/navigation.blade.php`, per the spec ("listed at the end of the
menu") — a plain `x-nav-link` / `x-responsive-nav-link`, not a dropdown, since it's a single
page. Add `$searchActive = request()->routeIs('projects.search.*');` next to the other
`*Active` booleans so it highlights consistently with the rest of the nav.

## Controller

A single-action-shaped controller, following the `StoryController` pattern (not a resource
controller — there's nothing to create/update/delete):

```php
class SearchController extends Controller
{
    public function index(SearchRequest $request, Project $project): View
    {
        $this->authorize('view', $project);

        $query = $request->validated('q');
        $mode = $request->validated('mode', SearchMode::All); // enum, see below

        $results = $query
            ? app(ProjectSearch::class)->search($project, $query, $mode)
            : null;

        return view('search.index', [
            'project' => $project,
            'query' => $query,
            'mode' => $mode,
            'results' => $results,
        ]);
    }
}
```

Why not a Form Request failing validation on empty query: an empty search box is the normal
landing state for this page (no `q` param at all on first visit from the nav link), not a
user error — a `required` rule would throw a jarring validation error on the very first visit.
`SearchRequest::rules()` instead treats `q` as `nullable|string|max:500` and `mode` as
`Rule::enum(SearchMode::class)`ith a default. The controller treats a null/blank `q` as "no
search yet" and skips querying entirely.

## Library choice: no new package — plain Eloquent `LIKE`, DB-agnostic

The spec asks to "suggest appropriate libraries." This app supports four DB drivers
(`sqlite`, `mysql`, `pgsql`, `sqlsrv` — see `config/database.php`, and
`documentation/architecture.md` § Database configuration), and the read-only
`DatabaseConfigurationController` shows the installed app can point at any of them. That
rules out driver-specific full-text features:

* MySQL `FULLTEXT` indexes and `MATCH ... AGAINST` — MySQL/MariaDB only.
* PostgreSQL `tsvector`/`to_tsquery` — Postgres only.
* SQLite FTS5 — needs a virtual table SQLite extension, and would require **duplicating**
  every searchable column into a shadow FTS table kept in sync via triggers or model events —
  real complexity for a feature the spec scopes as "for now."
* Laravel Scout (`laravel/scout`) — needs a driver (Meilisearch, Algolia, a DB driver of its
  own). This project has zero existing search infra and no reason yet to run/manage an
  external search service for what is currently a handful of rows per project.

**Recommendation: portable `LIKE '%term%'` queries via query builder `where(...)->orWhere(...)`
groups**, wrapped in one new class (see below). This works identically on all four supported
drivers, needs no new package, no new migration, and no background indexing job — the
project-scoped result sets here (one writer's acts/chapters/scenes/events/plotlines/codex
entries) are small enough that `LIKE` table scans are not a performance concern. If usage ever
grows past what `LIKE` can serve, that is exactly the trigger to introduce Scout — flag this
tradeoff explicitly rather than pre-building for it (KISS / no speculative abstraction, per
`CLAUDE.md`).

For **highlighting matched terms in a snippet**, no library is needed either: it's a small,
single-purpose string operation (find the term's byte offset case-insensitively, slice N
characters of context around it, wrap the term in `<mark>`, escape everything else). Pulling
in a snippet-extraction package for this would be over-engineering for one helper method.

## Where the logic lives

Per `CLAUDE.md` § *Where logic lives* — this is a "reusable, multi-step domain workflow"
(query construction across six models, three modes, highlighting), so it gets its own class,
not controller code:

* `app/Services/ProjectSearch.php` — orchestrates the six per-entity queries and assembles a
  `SearchResults` value object grouped by section (Timeline / Story / Codex).
* `app/Support/SearchSnippet.php` — the highlighting/snippet-extraction helper (`app/Support`
  matches where `PlotlineColors` already lives for small stateless helpers).
* `app/Enums/SearchMode.php` — `AllTerms` (AND) / `AnyTerm` (OR) / `ExactPhrase`, each with a
  `label()` for the form's radio/select, following the `SceneStatus`/`CodexEntryType` enum
  pattern (`label()`, backed string enum).

## Query shape

Each entity type is one query, eager-loading nothing extra (there's no relation to display
beyond the entity's own fields), scoped by `project_id` (`Act`, `Event`, `Plotline`,
`CodexEntry` are direct `project_id`; `Chapter` scopes via `act.project_id`, `Scene` via
`chapter.act.project_id` — mirroring the existing authorization-walk pattern):

```php
Act::where('project_id', $project->id)
    ->where(function ($q) use ($terms) {
        foreach ($terms as $term) {
            $q->orWhere('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        }
    })
    ->get();
```

For AND mode, each term becomes its own `where(function ...)` group (all must independently
match somewhere across the field set) rather than one `orWhere` chain. For exact-phrase mode,
skip term-splitting entirely and `LIKE '%<whole phrase>%'` once per field.

`ProjectSearch::search()` builds this per entity/field combination, then — for each matching
row — determines *which field(s)* matched (a second, cheap in-PHP check on the already-fetched
row, not a second query) so the result table can show the muted field-name label and extract
the right snippet. Six entity types × (field count) is a small, fixed number of queries per
search (no N+1 across rows), satisfying the "no N+1" acceptance criterion.

> [!NOTE]
> `Scene.contents` is Markdown and `Scene.notes` is sanitized rich HTML (see
> `Scene::renderedContents` / `SanitizesRichHtml` in `documentation/architecture.md`). Search
> against the **raw stored value** (Markdown source / HTML), not the rendered output — matches
> user intent ("search what I typed") and avoids re-rendering every scene per search. The
> snippet display must still escape HTML before highlighting (see `ui.md`) so raw HTML/Markdown
> syntax in a snippet can't break the results page.

## Authorization

Single check, same as `StoryController`: `$this->authorize('view', $project)` in the
controller, mirrored in `SearchRequest::authorize()` as
`$this->user()->can('view', $this->route('project'))` — per `CLAUDE.md` § Authorization.
No child-resource policies are needed since every result is scoped by the already-authorized
`$project`.
