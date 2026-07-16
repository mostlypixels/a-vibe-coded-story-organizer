---
status: draft
---

# Search result limiting and per-domain pagination

The `advanced_search` feature (`.specs/expanded/advanced_search/` at the time of writing)
renders every matching row for every entity/column on one page with no cap. On a large
project a broad or common search term could return an unbounded number of rows per column,
making the search page slow to render and hard to scan.

## Goals

* Cap the number of rows shown per result column on the main search page (e.g. Scenes,
  Events, Characters, ...) to a small fixed number (candidate: 5–10, to be settled during
  expansion).
* When a column has more matches than the cap, show a "See all N results" link instead of
  (or in addition to) the truncated list.
* That link leads to a **dedicated, paginated results page for one domain** (one entity type)
  within the same project and query — e.g. "all Scene matches for 'dragon'" — reusing
  Laravel's standard paginator (`->paginate()`), following the pagination conventions already
  used elsewhere in the app's index pages (query-string based, `?page=`).
* Preserve the current query (`q`) and mode (AND/OR/exact) when navigating from the capped
  search page into a domain's full paginated results, and back.

## Non-goals

* Does not change the search modes, field list, or highlighting logic defined by
  `advanced_search` — this is purely about result-count limiting and where the "see more"
  results live.
* Does not add infinite scroll or AJAX pagination — plain paginated `GET` pages, consistent
  with the rest of the app being non-AJAX (see `advanced_search`'s architecture notes on why
  the search page itself is a standalone controller, not AJAX).

## Rough approach

* Extract/reuse the per-entity query logic already built for `advanced_search`
  (`app/Services/ProjectSearch.php`) so the capped search page and the new per-domain page
  build results the same way — the difference is `->limit($cap)` / `->get()` vs.
  `->paginate($perPage)`.
* Likely a new route per domain, or one route parameterized by entity type, e.g.
  `GET /projects/{project}/search/{domain}` (mirroring the existing `{type}`-segment pattern
  used by `CodexEntryController`'s `whereIn('type', CodexEntryType::routeKeys())` route
  group), carrying `q` and `mode` through as query-string parameters.
* Depends on `advanced_search` being implemented first — this is an enhancement to it, not
  independent.
