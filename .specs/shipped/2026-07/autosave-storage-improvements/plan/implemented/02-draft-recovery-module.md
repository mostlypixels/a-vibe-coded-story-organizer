---
title: "Task 02 — Draft recovery module"
---

# Task 02 — Draft recovery module

## Scope

Build the new `resources/js/autosave/draft-recovery.js` module — the pure
`collectDraftEntries()` function and the `Alpine.data('draftRecoveryModal')` wrapper —
plus the `store.compareUrls[key]` wiring it depends on. This is the logic layer only;
no Blade template or `layouts/app.blade.php` mount yet (task 03).

Does **not** remove the existing inline per-field banner or its `field.js` state —
that stays in place until task 03 has a working replacement to swap in for.

## Depends on

Task 01 (`isDraftExpired`, the new `beforeunload`-timed `writeDraft` calls, and
`explicitLeaveRequested()` must exist first — `collectDraftEntries()` reads drafts
written by task 01's new code path).

## Key decisions already made

* **No new Alpine store.** `collectDraftEntries(fields, elements, compareUrls)` is a
  plain function taking the existing `Alpine.store('autosave')` maps as arguments —
  not a second store to keep in sync.
* **`store.compareUrls[key]`** is a new map on the existing `Alpine.store('autosave')`
  object (alongside `fields`/`elements`/`dirty`), populated in `autosaveField()`'s
  `init()` the same place `store.elements[key]` is already set, and removed in
  `destroy()` alongside the other per-field cleanup.
* Reuses `readDraft()`/`isDraftExpired()`/`triageDraft()` — no new draft-reading logic
  duplicated here; `collectDraftEntries()` just runs the existing per-field decision
  once per registered key instead of once per component's own `init()`.

## Where to make the change

`resources/js/autosave/field.js`:
* `init()`: add `store.compareUrls[this.key] = config.compareUrl ?? null;` alongside
  the existing `store.elements[this.key] = this.$el;`. `autosaveField()`'s config
  object gains a `compareUrl` field — task 03 is responsible for passing it from
  Blade (`autosave-field.blade.php` already computes `$compareUrl` today); this task
  just needs the store slot to exist and be settable.
* `destroy()`: add `delete store.compareUrls[this.key];` alongside the existing
  `delete store.fields[this.key]; delete store.elements[this.key]; delete
  store.dirty[this.key];`.

`resources/js/autosave/draft-recovery.js` (new file, mirrors `badge.js`'s
pure-logic-plus-Alpine-wrapper shape):
* `collectDraftEntries(fields, elements, compareUrls)`: for every key in `fields`,
  `readDraft(key)`; skip if absent or `isDraftExpired()`; run `triageDraft()` against
  the current server value (read from the field's own root element's `data-hash`
  attribute or an equivalent already-available source — check `autosave-field
  .blade.php`'s rendered `data-hash="{{ $hash }}"` attribute on the textarea/wysiwyg
  for how to reach the current server hash without a new prop); skip
  `drop-silently` results (nothing to recover); return `{ key, action, value,
  savedAt, compareUrl: compareUrls[key] }` for the rest.
* `Alpine.data('draftRecoveryModal', ...)`: `entries` populated from
  `collectDraftEntries()` on `init()`; `restore(key)` looks up
  `store.elements[key]`, sets its `querySelector('textarea')` value, dispatches a
  bubbling `input` event (same mechanic as today's `restoreDraft()`), then
  `clearDraft(key)`; `discard(key)` just `clearDraft(key)`; `restoreAll()`/
  `discardAll()` loop `entries` calling the single-entry versions.

Consult `architecture.md` §4 for the full reference shape.

## Tests to add

`resources/js/autosave/draft-recovery.test.js` (new):
* `collectDraftEntries()` with a mix of: a live `offer-restore` draft, a live
  `offer-compare-only` draft, an expired draft, and a key with no draft at all —
  returns entries only for the two live ones, with the correct `action`/`compareUrl`
  each.
* Confirms `isDraftExpired()` is consulted *before* `triageDraft()` — an
  intentionally-expired draft that would otherwise triage as `offer-restore` must
  still be excluded from the result.

`resources/js/autosave/field.test.js`: extend the existing `init()`/`destroy()`
teardown assertions to cover `store.compareUrls[key]` being set/removed alongside
`store.elements[key]`.

Run `npm run test` to verify — still no Blade/PHP change in this task.
