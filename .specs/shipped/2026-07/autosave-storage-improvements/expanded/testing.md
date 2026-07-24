---
title: Autosave Storage Improvements — Testing
---

# Testing

Follows this codebase's existing split (established by `badge.test.js`'s docblock,
reused by `navigation-guard.test.js`): **pure, DOM-free logic gets a vitest unit test;
real DOM/`beforeunload`/`localStorage`-timing gets a manual checklist.**

## `resources/js/autosave/store.test.js` — extend

* `isDraftExpired(draft, now)`: a draft with `savedAt` within the TTL → `false`; older
  than the TTL → `true`; exactly at the boundary — pick and assert one side explicitly
  (document which in the test itself, so a future TTL-value change doesn't silently
  flip an untested edge).
* `triageDraft()` itself is unchanged (§ `architecture.md` §2 — expiry is a pre-filter,
  not a new triage outcome) — no new cases needed there, just confirm existing tests
  still pass.

## `resources/js/autosave/field.test.js` — extend

Using the `createAlpineStub()` helper `data-loss-warnings` task 01 already added to
this file (per its resolution log — reuse it, don't re-derive):

* Typing in a field no longer calls `writeDraft`/touches `localStorage` (assert no
  `setItem` call on `input`, where today's `mirrorDraft()` would have fired one).
* Triggering `beforeunload` on a dirty field calls `writeDraft` once, with the current
  field value.
* Triggering `beforeunload` on a **clean** field (never edited, or already saved) does
  **not** call `writeDraft` — regression guard for `snapshotDraftIfDirty()`'s `!this
  .dirty` check.
* Dispatching `window`'s `autosave:explicit-leave` event, then triggering
  `beforeunload` on a dirty field, does **not** call `writeDraft` — the suppression
  path (`architecture.md` §3).
* `destroy()` still removes the `beforeunload` listener (extend the existing listener-
  teardown assertion pattern already in this file for `input`/`focusout`/`keydown`).

## `resources/js/autosave/draft-recovery.test.js` (new)

* `collectDraftEntries(fields, elements)` (pure): given a store shape with 2-3 mock
  field keys, some with live drafts (varying `action`: restore/compare-only), some
  with none, and one expired — returns entries only for the live, non-expired,
  non-`drop-silently` ones. Assert the expired entry and the "nothing to recover" entry
  are both absent from the result (not merely marked some other way).
* Confirms `isDraftExpired()` is actually consulted before `triageDraft()` runs (a
  mocked draft old enough to be expired but which *would* triage as `offer-restore` if
  checked — must still be excluded).

## Manual checklist (no automated coverage for real `beforeunload`/modal rendering,
same boundary this codebase already draws for the other two dialogs in this family)

* Edit a field, close the tab (or trigger `beforeunload` via devtools) → reopen the
  page → the recovery modal appears listing that field, with Restore/Discard.
* Same setup, but click "Leave anyway" on the `data-loss-warnings` in-app-navigation
  dialog instead of closing the tab → reopen the page → **no** recovery modal (the
  explicit-leave suppression worked).
* Edit a field, close the tab, wait past the TTL (or fake the system clock /
  pre-seed an old `savedAt` via devtools console), reopen → no modal, no leftover
  `localStorage` entry for that key.
* Two dirty fields on one page (e.g. `projects/edit`'s multi-field form), close the
  tab, reopen → one modal listing both fields, "Restore all"/"Discard all" acting on
  both.
* Dismiss the modal via Esc without choosing anything, reload the page → the modal
  reappears with the same entries (nothing was silently discarded).
* A field whose server value moved since the draft was written (simulate: edit in tab
  A, don't save; edit + save the same field in tab B; close tab A) → reopening tab A's
  page shows the entry as Compare/Discard only, never a bare Restore.

## Regression coverage — existing tests to re-verify, not modify

* `store.test.js`'s existing `triageDraft()` cases (`drop-silently`/`offer-restore`/
  `offer-compare-only`) must still pass unchanged — this spec adds a pre-filter, not a
  replacement.
* Any `AutosaveFieldComponentTest`-style PHP feature test that renders
  `autosave-field.blade.php` and asserts on the now-removed inline banner markup needs
  updating to stop expecting `data-autosave-draft-banner` — check `tests/Feature/
  AutosaveFieldComponentTest.php` for such an assertion before this ships.
