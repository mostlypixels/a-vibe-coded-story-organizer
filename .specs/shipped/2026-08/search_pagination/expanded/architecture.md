# Architecture

## SearchDomain enum — the eight result columns

New `app/Enums/SearchDomain.php`, backed string enum, mirroring `CodexEntryType` (which
already supplies `routeKeys()` for a `whereIn` route constraint). One case per result column:

```php
enum SearchDomain: string
{
    case Plotlines = 'plotlines';
    case Events = 'events';
    case Acts = 'acts';
    case Chapters = 'chapters';
    case Scenes = 'scenes';
    case Characters = 'characters';
    case Locations = 'locations';
    case Organizations = 'organizations';
}
```

It owns, in one place, everything the controller and view need per column so neither grows a
`match` ladder:

* `label(): string` — e.g. "Scenes", "Characters" (the column heading).
* `editRoute(): string` — the named edit route a row links to (`scenes.edit`, `codex.edit`, …),
  today duplicated as the view's `edit-route` prop.
* `rowsFrom(SearchResults $r): Collection` — pull this column off the result set (`$r->scenes`,
  `$r->characters`, …). This is the single mapping from domain → the existing named property.
* `nameField(): string` — `'title'` for Events, `'name'` otherwise (the display attribute).
* `routeKeys(): array` — `array_column(self::cases(), 'value')` for the route constraint.

> [!NOTE]
> Characters, Locations, and Organizations map to the same `codex.edit` route and all come
> from the one `CodexEntry` search, but they stay three distinct domains — the split by
> `type` is exactly what makes them separate columns.

## Route

One route, parameterized by domain, inside the existing `auth` group next to
`projects.search.index`:

```php
Route::whereIn('domain', SearchDomain::routeKeys())->group(function () {
    Route::get('/projects/{project}/search/{domain}', [SearchController::class, 'domain'])
        ->name('projects.search.domain');
});
```

The `whereIn` constraint makes an unknown `{domain}` a 404 before the controller runs — same
pattern the codex routes use for `{type}`. Bind `{domain}` to the enum by adding a route
model binding, or resolve it in the action with `SearchDomain::from($domain)` (the constraint
guarantees it is valid). Prefer implicit enum binding: type-hint `SearchDomain $domain` and
Laravel resolves it.

## Controller — second action on SearchController

Keep it on `SearchController` (the page's controller), not a new class:

```php
public function domain(SearchRequest $request, Project $project, SearchDomain $domain): View|RedirectResponse
```

Flow: `authorize('view', $project)` → read validated `q` + `mode` (same `SearchRequest`) →
**blank `q` redirects** to `projects.search.index` (nothing to paginate) → call
`ProjectSearch::searchDomain(...)` → return `search.domain` view.

`SearchRequest` is reused as-is: `q` nullable, `mode` optional-enum, `authorize()` already
walks the project via `ProjectPolicy::view`. No new request class.

## ProjectSearch — one new method, matching stays in PHP

Add a public method that searches a single domain and returns its full matched collection:

```php
/** @return Collection<int, SearchResultRow> */
public function searchDomain(Project $project, SearchDomain $domain, string $query, SearchMode $mode): Collection
```

Internals reuse the existing private machinery — do **not** duplicate the query/match logic:

* Story/Timeline domains (Plotlines, Events, Acts, Chapters, Scenes): call the existing
  per-entity `searchEntity(...)` with that entity's `*_FIELDS` and base query — the same
  builders `search()` already assembles. Factor the eight base-query + fields pairings out of
  `search()` so both methods draw from one source (a private `queryFor(SearchDomain, Project)`).
* Codex domains (Characters, Locations, Organizations): run the one `CodexEntry` search, then
  `codexRowsOfType(...)` for the domain's type — exactly what `search()` does per column.

`search()` (the main page) is unchanged in behaviour; it may be refactored to route its eight
columns through the same `queryFor` helper so there is a single definition of "how each domain
is searched."

> [!WARNING]
> Pagination is **PHP-side**, not SQL. `searchDomain` returns the whole matched `Collection`;
> the controller slices it into a page. A SQL `LIMIT/OFFSET` on the base query would page over
> *fetched* rows before PHP matching, giving wrong page contents and a wrong total. This is the
> spec's broken premise (`overview.md`), and the same reason `RevisionHistory::forEntity`
> builds its paginator by hand.

## Building the paginator

The controller (or a thin `searchDomainPaginated` wrapper) wraps the matched collection in a
`LengthAwarePaginator`, following `RevisionHistory::forEntity` verbatim:

```php
$matches = app(ProjectSearch::class)->searchDomain($project, $domain, $query, $mode);
$page = max(1, $request->integer('page', 1));
$perPage = /* config or const, recommend 25 — see open-questions */;

$paginator = new LengthAwarePaginator(
    $matches->forPage($page, $perPage)->values(),
    $matches->count(),
    $perPage,
    $page,
    ['path' => Paginator::resolveCurrentPath()],
);
$paginator->appends($request->only('q', 'mode')); // q/mode survive every page link
```

`appends()` (not `withQueryString()` — the collection paginator has no request) carries `q`
and `mode` onto every generated link.

## Authorization

Identical to search: `authorize('view', $project)` in the action, mirrored by
`SearchRequest::authorize()`. No policy change. Cover the non-owner 403 on the new route
(`testing.md`).

## Where the cap lives (main page)

The cap is a render concern, so `search()` stays as-is (full collections). The cap value is a
single constant/config read by the view layer. The `x-search.result-table` component takes a
`:cap` and renders `$rows->take($cap)` plus, when `$rows->count() > $cap`, the "See all N"
link built from `$domain` + current `q`/`mode` (`ui.md`). No per-column count query — the
collection is already in memory, so `->count()` is free.
