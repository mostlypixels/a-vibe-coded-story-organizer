# Overview

Enhancement to the shipped `advanced_search` (`.specs/shipped/2026-07/advanced_search/`).
The search page renders every matched row of every result column at once. A broad term on a
large project makes one page render hundreds of rows — slow and unscannable.

## Goals

* Cap each result column on the main search page to a small fixed number (recommend **5** —
  see `open-questions.md`).
* A capped column shows a **"See all N results"** link to a dedicated, paginated page for
  that one domain, same project and query.
* The per-domain page uses standard `?page=` pagination and carries `q` + `mode` on every
  link (paginator, "back to search", column links).

## Non-goals

* No change to search modes, fields, accent folding, or highlighting — pure result-count
  limiting plus a "see more" destination.
* No infinite scroll, no AJAX. Plain paginated `GET`, like every other index page.
* No new migration, model, or column. This feature is controller + view + one enum only.

## Domain = result column, not entity type

The search groups results into **eight columns**: Plotlines, Events, Acts, Chapters, Scenes,
Characters, Locations, Organizations. Characters/Locations/Organizations are all `CodexEntry`
rows split by `type`, but each is its own column. The per-domain page is keyed by **column**
(8 domains), so `/search/characters` shows only character matches — see
`architecture.md` § SearchDomain.

## The premise the spec assumes is already false

`spec.md` § Rough approach frames the difference as `->limit()` vs `->paginate()` at the query
level. It **cannot** work that way: the shipped `ProjectSearch` matches in PHP, not SQL (see
its class docblock). SQL `LIMIT`/`OFFSET` would count *fetched* rows, not *matched* ones, so a
paginated query would show wrong totals and skip matches. Both the cap and the pagination run
on the PHP-matched `Collection`. `architecture.md` owns the corrected approach; the precedent
is `RevisionHistory::forEntity`, which paginates a PHP-folded collection the same way.

## Acceptance criteria

* Main page: a column with > cap matches shows exactly `cap` rows and a "See all N results"
  link; a column with ≤ cap shows all rows and no link.
* "See all N" → per-domain page, same `q`/`mode`, showing that column's matches paginated.
* Paginator links, the domain-page column, and "back to search" all preserve `q` and `mode`.
* Non-owner gets 403 on the per-domain route (authorization walks the project, like search).
* Unknown domain segment → 404 (route constraint, before the controller runs).
* Blank/absent `q` on the per-domain page → redirect back to the main search page (there is
  nothing to paginate).
