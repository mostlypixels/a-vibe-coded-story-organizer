---
title: Data Loss Warnings — Testing
---

# Testing

Follows this codebase's existing split (see `badge.test.js`'s own docblock): **pure,
DOM-free logic gets a vitest unit test; anything touching a real `click`/`beforeunload`
listener or `window.location` is a manual checklist item**, same boundary
`autosave-with-revisions` already drew for `field.js`/`store.js`.

## `resources/js/navigation-guard.test.js` (new, vitest)

Exercises `shouldIntercept(event, anchor)` in isolation — a plain function, fake
`event`/`anchor` objects, no DOM needed:

* Plain left-click on a same-origin `<a href>` with no dirty-unrelated modifiers →
  `true`.
* `event.button !== 0` (middle/right click) → `false`.
* Each modifier key individually (`ctrlKey`, `metaKey`, `shiftKey`, `altKey`) → `false`.
* `anchor.target === '_blank'` → `false`.
* `anchor.hasAttribute('download')` → `false`.
* Cross-origin `anchor.href` → `false`.
* Same-page hash link (`href` differs only after `#`) → `false`.
* `anchor` is `null` (click didn't land on a link) → `false`.
* `event.defaultPrevented` already true (another handler claimed it) → `false`.

## `Alpine.store('autosave').isDirty()` — extend `store.test.js` or a new test file

Since `dirty`/`isDirty()` are additions to the *store shape* `field.js` builds up
(architecture.md §1), not to `store.js`'s pure exports, these are better covered as
**feature-level assertions inside `field.test.js`** (which already drives
`registerAutosaveField()` against a mocked Alpine instance) rather than a new pure-unit
file:

* Typing in a field (before the debounce fires) sets `Alpine.store('autosave')
  .dirty[key]` to `true`.
* A successful save (`STATES.SAVED` branch) clears it back to `false`.
* `destroy()` removes the key entirely (mirrors the existing `delete store.fields[key]`
  assertion already in this file).
* `isDirty()` returns `true` if *any* registered field is dirty, `false` if none are,
  and `false` when the store hasn't been created at all (no autosave fields on the
  page) — the call site in `navigation-guard.js` must handle this without throwing.

## Manual checklist (documented here, run once before shipping — no automated coverage
for real click/beforeunload/navigation, consistent with `field.js`'s own manual-only
items for retry timing and tab-focus replay)

* Edit a scene's contents, click a sidebar link within the 2-second debounce window →
  dialog appears; Cancel keeps you on the page and the field still autosaves normally
  afterward.
* Same setup, click **Leave anyway** → navigates to the clicked link's destination.
* A clean page (no edits) → clicking any in-app link navigates instantly, no dialog.
* Ctrl/Cmd-click, or a link with `target="_blank"`, on a dirty page → opens in a new
  tab/window with no dialog (the guard must never block a new-tab open).
* Dirty field, close the tab → native `beforeunload` prompt appears. Clean field, close
  the tab → no prompt.
* An external link (e.g. a documentation link, if any exist) on a dirty page → no
  dialog, since it isn't in-app navigation.

## Project delete confirmation — feature tests

New assertions in `tests/Feature/ProjectTest.php` (existing file, per `CLAUDE.md`'s
convention):

* A project with acts/plotlines/events/codex entries in some non-zero combination
  renders a delete-confirm string that lists only the non-zero categories.
* A brand-new project (only its un-deletable main plotline, per the existing invariant)
  renders the original unqualified text, not "0 acts, 0 events…".
* Authorization/deletion behavior itself is unchanged — no new positive/negative-auth
  test needed, `ProjectController::destroy()` and its policy are untouched.

## Act / Chapter "move or delete" — feature tests

New assertions in `tests/Feature/ActTest.php`/`ChapterTest.php`, plus new
`DestroyActRequestTest`/`DestroyChapterRequestTest`-style coverage if this codebase's
convention is to test Form Requests directly (check `tests/Unit`/`tests/Feature` for
precedent on the existing `Update*Request` classes before deciding placement):

* **Zero children**: deleting an act/chapter with no chapters/scenes behaves exactly as
  before — the original unqualified `confirm()`, no dialog, `DELETE` with no
  `move_children_to` deletes normally. A regression guard that this feature didn't
  change the empty-parent path.
* **Children, no other destination exists** (only act/chapter in the project): edit
  page renders the dialog with the informational "delete everything" text only, no
  `<select>`; submitting with no `move_children_to` deletes the parent and cascades as
  before.
* **Children, a destination exists, "Delete everything" chosen**: submitting with no
  `move_children_to` cascades exactly as before (children rows gone via FK cascade) —
  proves the new optional field is additive, not a behavior change to the existing
  path.
* **Children, "Move, then delete" chosen**: submitting with `move_children_to=<id>` —
  1. Every child's `act_id`/`chapter_id` now points at the destination.
  2. Every moved child's `position` is `>` every pre-existing child's position in the
     destination, and their *relative* order matches their original order (the
     `max(position) + 1`, incrementing invariant from `architecture.md` §4b).
  3. The source act/chapter is deleted; the moved children are **not** deleted
     (contrast with the cascade case — this is the core behavior this feature adds).
  4. Runs inside a transaction: a failure partway (e.g. a destination that fails
     re-validation) leaves neither a partial reassignment nor a deleted parent —
     assert via a forced-failure test if this codebase has a pattern for that
     (`DB::transaction` rollback), otherwise document as a manual check.
* **`move_children_to` validation** (`DestroyActRequest`/`DestroyChapterRequest`):
  a destination from a *different* project is rejected (403/422, matching
  `UpdateSceneRequest`'s existing `chapter_id` project-scoping precedent); the act's/
  chapter's own id as a destination is rejected (`Rule::notIn`); authorization mirrors
  `destroy()` itself — a non-owner gets 403 regardless of `move_children_to`.
* **Position invariant regression**: after a move, no two chapters share a `position`
  within the destination act (the gap this feature's grilling surfaced — `Chapter`'s
  `booted()` only assigns `position` on create, not on a plain `act_id` update).
