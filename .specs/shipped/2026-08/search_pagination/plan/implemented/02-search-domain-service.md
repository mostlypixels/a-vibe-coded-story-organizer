# 02 — config/search.php + ProjectSearch::searchDomain()

**Depends on:** 01 (needs `SearchDomain`).

## Scope

* Add `config/search.php` with `cap => 5` and `per_page => 20`, commented like
  `config/revisions.php` (why each number, what it counts).
* Add `ProjectSearch::searchDomain(Project $project, SearchDomain $domain, string $query,
  SearchMode $mode): Collection<int, SearchResultRow>` — returns the **full** matched
  collection for one domain, in the entity's existing natural order.
* Refactor `ProjectSearch::search()` internals so both it and `searchDomain()` draw each
  column's base query + field map from one private source (a `queryFor(SearchDomain, Project)`
  or equivalent). Story/Timeline domains route through the existing `searchEntity(...)`; codex
  domains run the single `CodexEntry` search then `codexRowsOfType(...)`.

## Explicitly NOT in this task

* No pagination, no `LengthAwarePaginator` (task 03 builds it from this collection).
* No route, controller action, or view (task 03).
* No cap applied anywhere (task 04); the config value is added here but read later.

## Key decisions (binding)

* `searchDomain()` returns the whole collection — pagination is PHP-side and lives in the
  controller (task 03). Do **not** add SQL `LIMIT/OFFSET`: matching is in PHP (see the class
  docblock and `expanded/architecture.md` → the warning).
* `search()` behaviour is unchanged; the refactor only removes duplication between the eight
  column definitions and `searchDomain()`.

## Consult

`expanded/architecture.md` → ProjectSearch; `app/Services/ProjectSearch.php` (existing
`searchEntity`, `codexRowsOfType`, the `*_FIELDS` maps).

## Tests

* `searchDomain()` per domain: returns only that domain's matches; codex split holds
  (`Characters` excludes Location/Organization matches from the shared table); natural order
  preserved.
* `search()` regression: `SearchTest` stays green (the refactor changed no output).
* Probe or unit-assert the config keys load (`config('search.cap')` etc.).
