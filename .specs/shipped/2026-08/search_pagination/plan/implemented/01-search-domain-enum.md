# 01 — SearchDomain enum + domain-driven result table

**Depends on:** none.

## Scope

* Add `app/Enums/SearchDomain.php` — backed string enum, one case per result column:
  `Plotlines, Events, Acts, Chapters, Scenes, Characters, Locations, Organizations`.
* Methods it owns (single source of the per-column facts):
  * `label(): string` — column heading text.
  * `editRoute(): string` — `plotlines.edit` / `events.edit` / … / `codex.edit` for the three
    codex domains.
  * `nameField(): string` — `'title'` for Events, `'name'` otherwise.
  * `rowsFrom(SearchResults $r): Collection` — the matching named property (`$r->scenes`, …).
  * `routeKeys(): array` — the backing values, for task 3's route constraint (add now, used later).
* Refactor `resources/views/components/search/result-table.blade.php` and
  `resources/views/search/index.blade.php` to pass `:domain` and read `editRoute`/`nameField`
  off the enum instead of the eight hand-written literal pairs.

## Explicitly NOT in this task

* No cap, no "See all" link (task 04). `result-table` still renders all rows.
* No `searchDomain()` service method, no config file (task 02).
* No new route, controller action, or view (task 03).

## Key decisions (binding)

* Behaviour of the shipped search page is unchanged — this is a pure refactor. The existing
  `SearchTest` must stay green with no assertion changes beyond call-site props.
* The enum is the one definition of a column's route/name-field/rows mapping — see
  `expanded/architecture.md` → SearchDomain.

## Consult

`expanded/architecture.md` → SearchDomain; `expanded/ui.md` → `result-table`. Current call
sites: `search/index.blade.php` (eight `<x-search.result-table>` tags).

## Tests

* Unit test `SearchDomain`: `rowsFrom()` returns the right `SearchResults` property per case;
  `editRoute()`/`nameField()` equal the values `search.index` passed before the refactor
  (regression guard on the literal → enum move).
* `SearchTest` stays green (existing coverage proves the refactor kept behaviour).
