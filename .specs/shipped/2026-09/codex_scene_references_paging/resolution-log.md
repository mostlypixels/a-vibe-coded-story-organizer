# Codex ↔ scene references: capped lists with a "see all" page — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Both codex cards take one table shape**, linking `scenes.show`. The `codex/edit` card
  loses its `<ul>` and its `scenes.edit` link, and the `codex/show` card gains an Event
  column. `expanded/ui.md`'s "one shared row component" could not hold otherwise: the two
  cards differed in markup *and* link target.
- **The cap lives inside `codex/partials/referenced-entries.blade.php`**, which now takes
  `$scene`. The partial has a third caller the expanded docs missed —
  `SceneCodexEntryController::store()` renders it to JSON for the editor's AJAX refresh.
  Capping outside it would lose the cap after any quick-add.
- **Entry rows show name + type, no cover.** `expanded/ui.md` asked for a thumbnail "where
  the existing sidebar shows one"; no such thumbnail exists. The `->with('cover')` eager
  load on the scene side was dead and is deleted.
- **The Book column uses `$project->books()->count() > 1`**, not `Book::hasOwnName()` as
  `Breadcrumbs` does. A breadcrumb hides a *level*; a table column hides a *value*, and a
  half-blank column reads as missing data rather than as "this book has no name".
- **Back links are fixed to the read page** — `codex.show` and `scenes.show` — in both
  directions. `expanded/ui.md` proposed `scenes.edit` for one of them, with no stated
  reason for the inconsistency.
- **The `book.position` fix is task 01, on its own**, so it lands before any new view reads
  the order.

## Deviations from the spec/plan

- **Task 04b was added after task 04**, outside the original six-task decomposition. Task
  04 left the same five-column table head — the conditional Book heading, the `scene-row`
  loop, and the footer `colspan` arithmetic — written out in `codex/show.blade.php`,
  `codex/edit.blade.php` and `references/scenes.blade.php`. Three callers, so
  `CLAUDE.md`'s "not until a second caller" rule was met twice over, and extracting before
  task 05 built the entry-side equivalent was cheaper than after.

## Issues → resolutions

_None yet._
