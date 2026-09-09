# 04b — Extract the scene table

Added after task 04, not in the original decomposition. Task 04 left the same five-column
table head written out three times.

## Scope

- `resources/views/components/references/scene-table.blade.php`: the `x-table`, the
  conditional Book heading, the `scene-row` loop, and an optional footer link.
- Props: the scenes to render, `$showBook`, and an optional `$seeAllRoute` + total count.
  When the route is absent the footer is absent — the full page passes no route.
- Rewrite all three call sites onto it: `codex/show.blade.php`, `codex/edit.blade.php`,
  `references/scenes.blade.php`.
- The component owns the `colspan` arithmetic, which is duplicated in two places today.
- Not in scope: the entry side. Task 06 may want the same treatment; decide there.

## Depends on

04.

## Key decisions

- Extract now, before task 05 builds the entry-side equivalent — three callers already
  exist, so `CLAUDE.md`'s "not until a second caller" is satisfied twice over.
- Pure refactor. Rendered HTML must not change; the task 03 and 04 tests are the proof.
- The cap stays at the call site, not in the component. The full page renders a paginated
  slice, the cards render `->take(config('search.cap'))` — the component takes whatever
  it is handed.

## Tests

No new tests. Every test added by tasks 03 and 04 must pass untouched — that is the point.
