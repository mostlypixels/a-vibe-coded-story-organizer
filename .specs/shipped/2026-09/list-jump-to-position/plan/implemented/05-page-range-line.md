# 05 — Page-range line

"Chapter 214 — Ash and Rust to Chapter 231 — Salt and Thorn", above the pagination bar.
Answers "where am I" on every page load, jump or not — the one part of the rejected sidebar
design worth keeping.

## Scope

**In:**

- `resources/views/components/pagination-bar.blade.php` — new optional `range` prop.
- `SceneController::index` / `ChapterController::index` — build `$pageRange`, pass it to the
  view.
- both index Blade files — pass `:range="$pageRange"` to the bar.
- `documentation/interface/components.md` — note `range` on the bar and `highlighted` on the
  row (03 added the prop; document both here).
- tests.

**Out:** nothing deferred. This is the last task.

## Depends on

03, 04.

## Key decisions

- **No extra query.** Build the string from the already-hydrated, already-eager-loaded
  paginator: `$scenes->first()->chapter` / `->last()->chapter`, `$chapters->first()->act` /
  `->last()->act`. The relations are loaded by `with()` today; do not add a query.
- `null` when the page is empty, and `null` when `$sort !== 'position'` — a name-sorted page
  covers no contiguous range.
- One name, no "to", when first and last are the same group.
- **The bar is shared by nine lists.** `range` defaults to `null` and a bar without it must
  render byte-identically to today. `basis-full` item ahead of `x-row-range` in the existing
  `flex flex-wrap`; nothing else moves.

## Tests

- one group on the page → one name, no "to"; two → first and last.
- `sort=name` → no range line; empty list → no range line.
- **the shared-component regression:** one test over `ListPaginationTest::indexes()`
  asserting every other index still renders its bar with no range. This is the test that
  earns this task its place last.

## Consult

`../expanded/ui.md` → *The page-range line*. `../expanded/architecture.md` → *The page-range
line*.
