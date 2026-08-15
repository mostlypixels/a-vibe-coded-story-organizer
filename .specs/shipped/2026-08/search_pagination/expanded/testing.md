# Testing

Extend `tests/Feature/SearchTest.php` (the shipped `advanced_search` test) — same style: plain
PHPUnit, `RefreshDatabase`, factories, `actingAs`, `route()` helper, never raw URLs.

Factory helper: seed a project with **more than the cap** matching rows for at least one domain
(e.g. `cap + 3` scenes all containing "dragon") so both the cap and pagination have something
to bite on.

## Main page — the cap

* A column with > cap matches renders exactly `cap` rows **and** a "See all N results" link
  whose href carries the current `q` and `mode`.
* A column with ≤ cap matches renders all rows and **no** "See all" link.
* N in the link equals the true total match count, not the capped count.
* The cap does not leak across columns — capping Scenes leaves an under-cap Events column full.

## Per-domain page

* Owner, valid domain, matching `q`: 200, shows that domain's matches, paginated.
* `?page=2` shows the next slice; page links preserve `q` and `mode` (assert the query string
  on a rendered link, or assert `$paginator` was built with the right `appends`).
* Only the requested domain's rows appear — a Scene match never shows on `/search/characters`.
* Codex split holds: `/search/characters` excludes Location/Organization matches even though
  all three share the `CodexEntry` table.
* Total count / last page reflect all matches (PHP-matched, not a SQL `LIMIT` miscount) — page
  past the end shows the empty-state line, not a 500.

## Authorization & validation

* Non-owner → 403 on `projects.search.domain` (cover every… at least one domain is enough given
  one shared `authorize` call).
* Unknown domain segment (`/search/nonsense`) → 404 (route constraint).
* Blank/absent `q` → redirect to `projects.search.index` (nothing to paginate).
* Invalid `mode` → validation error (reuses `SearchRequest`, already tested for the main page —
  one assertion here confirms the domain route runs the same request).

## Enum

Unit-test `SearchDomain` if the mapping is non-trivial: `rowsFrom()` returns the matching
`SearchResults` property for each case; `editRoute()`/`nameField()` match the values the
current `search.index` passes (a regression guard against the call-site → enum move).
