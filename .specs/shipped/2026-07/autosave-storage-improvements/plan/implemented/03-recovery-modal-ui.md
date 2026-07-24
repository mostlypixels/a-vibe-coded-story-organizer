---
title: "Task 03 — Recovery modal UI"
---

# Task 03 — Recovery modal UI

## Scope

Build the new page-level `<x-autosave-draft-recovery-modal>` Blade component, mount it
once globally in `layouts/app.blade.php`, wire `autosave-field.blade.php`'s
`$compareUrl` into the new `config.compareUrl` task 02 added a store slot for, and
**remove** the old inline per-field banner entirely — both its Blade markup and the
`field.js` state that backed it (`draftAction`/`draftValue`/`draftSavedAt`/
`restoreDraft()`/`discardDraft()`/`checkForDraft()`'s call in `init()`).

This is the only task in the plan touching Blade/rendered UI — see "Runtime
verification" below before considering it done.

## Depends on

Task 02 (`draft-recovery.js`'s `collectDraftEntries()`/`Alpine.data
('draftRecoveryModal')` and `store.compareUrls` must exist and work first).

## Key decisions already made

* **The inline banner is deleted, not adapted.** `autosave-field.blade.php`'s
  `data-autosave-draft-banner` block is removed outright; the field component
  renders nothing for draft recovery once this task lands.
* **Reuses `<x-dialog>`**, same family as `data-loss-warnings`' navigation-guard
  dialog and `delete-with-move-dialog`.
* **Closing the modal (Esc/backdrop) must not discard anything** — only an explicit
  Restore/Discard/Restore-all/Discard-all click, or TTL expiry on a later load,
  removes a draft.
* **Known trap, already hit twice in the sibling `data-loss-warnings` feature**:
  `<x-dialog>` does not merge `$attributes` onto its inner `<x-modal>` — an `x-data`
  placed directly on `<x-dialog>` is silently dropped. Wrap the whole `<x-dialog>`
  block in a plain `<div x-data="draftRecoveryModal()">` instead (see
  `layouts/app.blade.php`'s existing `unsaved-changes-guard` mount for the pattern to
  copy). Also confirm any dialog *trigger* element has its own `x-data=""` if it sits
  outside an existing Alpine scope — the other trap that same feature's resolution
  log recorded.

## Where to make the change

* **New** `resources/views/components/autosave-draft-recovery-modal.blade.php` — see
  `ui.md`'s reference markup: a `<template x-for="entry in entries">` list with
  per-entry Restore (only when `entry.action === 'restore'`)/Compare (only when
  `entry.action === 'compare-only'`, linking `entry.compareUrl`)/Discard, plus a
  footer with Discard All and a conditionally-shown Restore All.
* `resources/views/layouts/app.blade.php`: mount `<x-autosave-draft-recovery-modal
  />` once, next to `<x-autosave-status-badge />` and the existing
  `unsaved-changes-guard` dialog wrapper.
* `resources/views/components/autosave-field.blade.php`: pass `compareUrl:
  @js($compareUrl)` into the `x-data="autosaveField({...})"` config (the value is
  already computed at `:38-40`, just not currently passed through); **remove** the
  entire `data-autosave-draft-banner` block (`:82-117`) and its three `x-show`
  branches.
* `resources/js/autosave/field.js`: remove `draftAction`/`draftValue`/
  `draftSavedAt`/`restoreDraft()`/`discardDraft()` from the `autosaveField()` factory,
  and the `this.checkForDraft();` call in `init()` (the function itself, plus the
  TTL-aware logic task 01 added to it, moves to being called from
  `draft-recovery.js`'s `collectDraftEntries()` instead — check whether
  `checkForDraft()` survives as an exported helper `draft-recovery.js` calls, or is
  fully inlined there; either is fine as long as the TTL pre-filter behavior from
  task 01 is preserved).
* `resources/js/app.js`: register `draftRecoveryModal` alongside the existing
  `registerAutosaveField(Alpine)`/`registerNavigationGuard(Alpine)` calls.

## Tests to add

PHP feature tests (check `tests/Feature/AutosaveFieldComponentTest.php` first, per
`open-questions.md` #4 — confirmed this session to have no existing assertion on the
banner markup, but re-check once this task's diff exists):
* Rendering an edit page no longer includes `data-autosave-draft-banner` markup.
* `<x-autosave-draft-recovery-modal>` renders (e.g. assert the dialog's `name`
  attribute/wrapper markup is present) on a page using `x-autosave-field`.

JS (extend `resources/js/autosave/draft-recovery.test.js` from task 02, or a small
addition if the Alpine-wrapper half needs its own coverage beyond the pure function):
* No new pure-logic surface expected here beyond task 02's — this task is mostly
  wiring, so lean on the manual checklist below for the parts only a real DOM/browser
  can verify.

## Runtime verification (required — this task touches Blade/Alpine wiring)

Build the frontend (`npm run build`) and drive the app in a real browser
(`run-imagoldfish` skill), following `data-loss-warnings`' precedent of not trusting a
green test suite alone for Alpine/Blade wiring:
* Edit a field, trigger `beforeunload` (or close/reopen the tab), reload the page →
  the new modal appears listing that field, with working Restore/Discard.
* Restore actually repopulates the field and marks it dirty again (autosave picks it
  up normally afterward).
* Esc/backdrop-close the modal without choosing, reload again → the modal reappears
  with the same entries (nothing silently discarded).
* Two dirty fields on one page (e.g. `projects/edit`) → one modal listing both,
  Restore All/Discard All acting on both.
* Confirm the old inline banner no longer appears anywhere, even mid-typing.
