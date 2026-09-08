---
title: Codex Entry From The Scene Editor — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-quick-entry-endpoint.md` | `scenes.codex-entries.store`, the Form Request, `CodexEntrySaver`'s rescan switch, the shared list partial. |
| 02 | `02-awaitable-autosave-flush.md` | `flush()` returns its promise; the store gains `flush(key)`. Independent of 01. |
| 03 | `03-dialog-and-sidebar.md` | The dialog, the sidebar button, the list swap, the confirmation line. Depends on 01 and 02. |
| 04 | `04-slash-menu-item.md` | A `New codex entry` item in the editor's slash menu, opening the same dialog. Depends on 03. |

## Binding design decisions (do not re-litigate)

Resolved in the grill; recorded in `../resolution-log.md`.

1. **One name and one type.** No description, aliases, tags or attributes. If it needs a
   second field it needs the real form.
2. **The endpoint skips the project-wide rescan** and syncs only the current scene.
   `syncProject()` mid-paragraph is the cost this feature exists to avoid. The stale-pivot
   debt is accepted and recorded in this feature's `standing-issues.md`.
3. **The endpoint answers with the scene's whole refreshed reference list**, not just the
   new entry — `syncScene()` may have added or dropped others.
4. **Blade stays the only template for that list.** The endpoint returns the list's rendered
   HTML and the client swaps it in. No Alpine `x-for` twin to keep in step, and the sidebar
   still renders with JavaScript off.
5. **A success always shows a confirmation line** naming the created entry, with a link,
   separate from the references list. A name not yet written into the prose will not appear
   in that list, and a silent close reads as a failure.
6. **A duplicate name of the same type is refused**, with a link to the existing entry. The
   full form only warns because it has a form left to read the warning on; this has none.
   Note the divergence where the rule lives.
7. **The autosave flush runs with `runMatcher: false`.** The entry does not exist yet, so a
   matcher run there finds nothing, and the endpoint syncs after creating.
8. **Two triggers, one code path** — sidebar button and slash menu item. A selection
   popover is deferred; the app has that pattern nowhere else.
9. **An abandoned name-only entry is fine.** One row, visible and deletable in the codex
   list. No nag, no cleanup job.
10. **`SceneReferenceMatcher`, `CodexEntryController`, the codex policies and
    `AttributeTimeline` are untouched.**

## Core invariants every task must preserve

* **The scene is never saved by this flow.** `updated_at`, `contents` and `word_count` must
  come through a create untouched. The autosave flush is the writer's own pending save, not
  one this feature invents.
* **Unsaved prose is never lost.** The dialog never closes on an error, and the typed name
  survives a refusal.
* **Focus returns to the editor at the previous selection** on close, success or cancel.
  That is the "back to the paragraph" goal and the part most easily dropped.
* **Authorization walks to the owning project** — `update` on
  `$scene->chapter->act->book->project`, mirrored in the Form Request. A non-owner and a
  scene from another project both get 403.
* **Existing callers of `CodexEntrySaver::create()` keep the project rescan.** The new
  parameter defaults to the old behaviour.
