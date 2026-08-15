# 04 — Cap the main-page columns + "See all N" link

**Depends on:** 01 (enum), 02 (config `cap`), 03 (the `projects.search.domain` route to link to).

## Scope

* `x-search.result-table`: render `$rows->take(config('search.cap'))` instead of all rows.
  When `$rows->count() > cap`, render a "See all N results" link below the table to
  `route('projects.search.domain', ['project' => $project, 'domain' => $domain->value,
  'q' => $query, 'mode' => $mode->value])`, styled as a standard `text-link`.
* Pass `$project`, `$query`, `$mode` into the component (already in `search.index` scope).
* No "See all" link when `count <= cap`; the column shows every row.

## Explicitly NOT in this task

* No new query — the count is `->count()` on the already-materialized collection. `search()`
  is unchanged.

## Key decisions (binding)

* Cap is a **render** concern; `ProjectSearch::search()` still returns full collections. The
  cap only limits rows shown — see `expanded/open-questions.md` #4.
* N in the link text is the true total match count, not the capped count.
* Link appears only when `count > cap` (a column of exactly `cap` is not truncated).

## Consult

`expanded/ui.md` → `result-table` / `search.index`; `expanded/open-questions.md` #3, #8.

## Tests

Extend `SearchTest`. Seed `cap + 3` matches in one domain: page renders exactly `cap` rows plus
a "See all N" link whose href carries `q`/`mode` and whose N equals the true total. A column
with `<= cap` matches renders all rows and no link. Capping one column does not truncate a
smaller sibling column.
