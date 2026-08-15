# search_pagination — plan overview

Enhancement to the shipped `advanced_search`. Cap each result column on the main search page,
and add a dedicated, paginated per-domain page for "see all". No migration, no model, no column.

Read `expanded/` for detail; this manual is never implemented or moved.

## Execution order

1. **01-search-domain-enum** — add `SearchDomain` enum; refactor `x-search.result-table` +
   `search.index` to be domain-driven. Behaviour unchanged, `SearchTest` stays green.
2. **02-search-domain-service** — add `config/search.php` (`cap`, `per_page`) and
   `ProjectSearch::searchDomain()`; refactor the eight column definitions into one internal
   `queryFor` source. Pure service, no HTTP.
3. **03-domain-page** — domain route + `SearchController::domain` action + `search.domain` view
   + PHP-side pagination + blank-`q` redirect.
4. **04-main-page-cap** — cap each column at `cap` rows and add the "See all N results" link
   (route from task 3 now exists to point at).

## Binding decisions (do not re-litigate)

* **Config, not constants.** `config/search.php` holds `cap => 5` and `per_page => 20`,
  mirroring `config/revisions.php`. Values are read through config, never inlined.
* **Domain = result column, not entity type.** Eight domains; Characters/Locations/
  Organizations are separate domains that all come from the one `CodexEntry` search split by
  `type` (see `expanded/architecture.md` → SearchDomain).
* **Pagination is PHP-side.** `searchDomain()` returns the full matched `Collection`; the
  controller slices it into a hand-built `LengthAwarePaginator`, exactly like
  `RevisionHistory::forEntity`. **Never** a SQL `LIMIT/OFFSET` on the base query — matching runs
  in PHP, so SQL paging miscounts and skips matches.
* **Enum owns the per-column facts.** `label`/`editRoute`/`nameField`/`rowsFrom`/`routeKeys`
  live on `SearchDomain`; `search.index` and the domain page read one definition, not two.
* **Blank `q` on the domain page → redirect** to `projects.search.index`, carrying `mode`. The
  domain page has no search box; it is a "see more" destination only.
* **"See all N" appears only when `count > cap`.** A column that fits shows every row, no link.

## Invariants every task preserves

* **Authorization walks the project.** Every new endpoint calls `authorize('view', $project)`
  and mirrors it in the reused `SearchRequest::authorize()`. Cover the non-owner 403.
* **Unknown domain → 404** via the `whereIn('domain', SearchDomain::routeKeys())` route
  constraint (same pattern as the codex `{type}` routes), before the controller runs.
* **Matching semantics unchanged.** No task touches accent folding, term matching, modes, or
  highlighting — those are `advanced_search`, frozen here.
* **`q`/`mode` survive every hop.** Paginator links, the "See all N" link, and "back to search"
  all carry the current query and mode.
