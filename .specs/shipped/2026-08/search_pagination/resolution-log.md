# search_pagination — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

* Config over constants: `config/search.php` holds `cap => 5` and `per_page => 20`, mirroring
  `config/revisions.php` (which uses per_page 20).
* `SearchDomain` enum owns the per-column facts (`label`/`editRoute`/`nameField`/`rowsFrom`/
  `routeKeys`); the shipped `search.index` + `result-table` are refactored onto it first
  (task 01) so both pages read one definition.
* Domain page with blank `q` redirects to `projects.search.index` (carrying `mode`) — it is a
  "see more" destination with no search box of its own.
* Task order fixed so the "See all N" link (task 04) lands only after its route exists (task 03).

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
