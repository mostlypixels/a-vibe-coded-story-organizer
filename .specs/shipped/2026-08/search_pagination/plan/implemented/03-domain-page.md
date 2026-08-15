# 03 — Domain route, controller action, paginated view

**Depends on:** 01 (enum), 02 (`searchDomain()`, config).

## Scope

* Route: `GET /projects/{project}/search/{domain}` → `SearchController@domain`, named
  `projects.search.domain`, inside the existing `auth` group, wrapped in
  `Route::whereIn('domain', SearchDomain::routeKeys())` so an unknown domain 404s before the
  controller. Bind `{domain}` to the enum (implicit enum binding: type-hint `SearchDomain`).
* `SearchController::domain(SearchRequest $request, Project $project, SearchDomain $domain)`:
  `authorize('view', $project)` → read validated `q`/`mode` → **blank `q` redirects** to
  `projects.search.index` (carry `mode`) → `searchDomain()` → build a `LengthAwarePaginator`
  from the collection (`forPage`, `appends($request->only('q','mode'))`, path via
  `Paginator::resolveCurrentPath()`) → return `search.domain`.
* View `resources/views/search/domain.blade.php`: page heading naming the domain + query, a
  "← Back to search" link (carries `q`/`mode`), one `x-table` (same head as `result-table`)
  looping `$paginator` through `x-search.result-row`, `{{ $paginator->links() }}` below, and a
  "No more results" line when a stale `?page=` overshoots.

## Explicitly NOT in this task

* No change to the main page or its columns — the cap and "See all N" link are task 04. This
  page is reachable by direct URL and fully testable on its own.

## Key decisions (binding)

* Pagination is PHP-side — slice the collection from task 02; never a SQL limit. Follow
  `RevisionHistory::forEntity` for the `LengthAwarePaginator` construction.
* Reuse `SearchRequest` unchanged (`q` nullable, `mode` optional enum, project-walk authorize).
* Blank `q` → redirect, not an empty render (the page has no search box).

## Consult

`expanded/architecture.md` → Route / Controller / Building the paginator;
`expanded/ui.md` → `search.domain`; `app/Services/RevisionHistory.php::forEntity` (paginator
precedent); `revisions/index.blade.php:102` (`->links()` usage).

## Tests

Extend `SearchTest`. Owner + valid domain + matching `q`: 200, shows that domain's matches
paginated. `?page=2` slices correctly; page links carry `q`/`mode`. Only the requested domain
appears; codex split holds. Total/last page reflect all PHP-matched rows (no SQL miscount);
overshoot page → empty-state line, not 500. Non-owner → 403. Unknown domain → 404. Blank `q` →
redirect to `projects.search.index`.
