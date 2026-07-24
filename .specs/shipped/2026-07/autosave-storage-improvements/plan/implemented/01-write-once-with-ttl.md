---
title: "Task 01 — Write once, with TTL"
---

# Task 01 — Write once, with TTL

## Scope

Change *when* a draft is written (once, at `beforeunload`, instead of on every
keystroke) and gate *reads* on a 4-hour TTL, plus suppress the write when the
departure was an explicit, informed "leave anyway" via `data-loss-warnings`' nav
guard.

Does **not** touch the recovery UI at all — the existing inline per-field banner
(`autosave-field.blade.php`'s `data-autosave-draft-banner`, `field.js`'s
`draftAction`/`checkForDraft()`/`restoreDraft()`/`discardDraft()`) stays exactly as it
is and keeps working, just against the new write timing and TTL. Removing that UI is
task 03's job, after the new modal (task 02) exists to replace it.

## Depends on

Nothing — first task in the sequence.

## Key decisions already made

* TTL is **4 hours, a flat duration** — not calendar-day, does not reset at midnight.
* **One write per dirty field**, same key format and `writeDraft()` call as today —
  only the call site moves from `onInput()` to a new `beforeunload` listener.
* `explicitLeavePending` is a **module-level flag in `field.js`**, set by a single
  `window.addEventListener('autosave:explicit-leave', ...)` registered once at module
  load (not inside `registerAutosaveField()`, no guard needed — an ES module body only
  runs once). **Never reset** — this app has no client-side routing, so once set, the
  page is already unloading.
* Expiry is a **read-time pre-filter**, not a new `triageDraft()` outcome — the
  existing three-way result shape is unchanged.

## Where to make the change

`resources/js/autosave/store.js`:
* Add `export const DRAFT_TTL_MS = 4 * 60 * 60 * 1000;` and `export function
  isDraftExpired(draft, now = Date.now())`.

`resources/js/autosave/field.js`:
* `onInput()` (currently calls `this.mirrorDraft()`): **remove** that call. Keystrokes
  still set `dirty`/`store.dirty[key]` and restart the debounce timer.
* Add, at module top level (outside `registerAutosaveField()`):
  ```js
  let explicitLeavePending = false;
  window.addEventListener('autosave:explicit-leave', () => { explicitLeavePending = true; });
  function explicitLeaveRequested() { return explicitLeavePending; }
  ```
* `init()`: add a `beforeunload` listener (`this._onBeforeUnload = () =>
  this.snapshotDraftIfDirty();`), removed in `destroy()` alongside the existing
  `focus`/`visibilitychange` teardown.
* New method `snapshotDraftIfDirty()`: same body as today's `mirrorDraft()`, guarded
  by `if (!this.dirty || explicitLeaveRequested()) return;` first.
* `checkForDraft()`: before calling `triageDraft()`, check `isDraftExpired(draft)` —
  if expired, `clearDraft(this.key)` and return (identical to the existing "no draft"
  early return), never reaching `triageDraft()`.

Consult `architecture.md` §1–3 for the exact reference implementation.

## Tests to add

`resources/js/autosave/store.test.js`:
* `isDraftExpired()`: within TTL → `false`; older than TTL → `true`; document which
  side of the exact boundary is asserted.

`resources/js/autosave/field.test.js` (reuse the `createAlpineStub()` helper
`data-loss-warnings` task 01 already added here):
* Typing no longer calls `writeDraft`/touches `localStorage`.
* Triggering `beforeunload` on a dirty field calls `writeDraft` once with the current
  value.
* Triggering `beforeunload` on a clean field does not call `writeDraft`.
* Dispatching `autosave:explicit-leave` then triggering `beforeunload` on a dirty
  field does not call `writeDraft`.
* `destroy()` removes the `beforeunload` listener (extend the existing teardown
  assertion pattern for `input`/`focusout`/`keydown`).
* `checkForDraft()` with an expired draft clears it and does not set `draftAction`
  (reuse the existing inline-banner state, since it's still present in this task).

Run `npm run test` to verify — this task touches no PHP, no Blade.
