---
title: Autosave Storage Improvements — Architecture
---

# Architecture

Grounded in the current (post-`data-loss-warnings`) `resources/js/autosave/field.js`,
`resources/js/autosave/store.js`, and `resources/js/navigation-guard.js`, all read this
session.

## 1. Write once, at departure — per field, not aggregated

Each `x-autosave-field` instance already owns its own `localStorage` key
(`storageKeyFor()`, `entity:id:field` or `new:entity:parentId:field`) and its own
`dirty` boolean. The minimal-change answer to `spec.md`'s open question ("one write per
dirty field, or a single aggregated entry keyed by page") is **one write per dirty
field, each field's own `beforeunload` listener** — this requires no new storage
format, no aggregation key, and no cross-field coordination; it's the exact same
`writeDraft(this.key, {...})` call that exists today, just moved from `onInput()` to a
`beforeunload` handler.

`resources/js/autosave/field.js` changes:

* `onInput()` (`:226-237`): **remove** the `this.mirrorDraft()` call. Keystrokes still
  set `dirty`/`store.dirty[key]` and (re)start the debounce timer — only the
  `localStorage` write moves.
* `init()` (`:176-193`): add a `beforeunload` listener alongside the existing
  `focus`/`visibilitychange` ones:
  ```js
  this._onBeforeUnload = () => this.snapshotDraftIfDirty();
  window.addEventListener('beforeunload', this._onBeforeUnload);
  ```
  removed in `destroy()` alongside the other listener teardown.
* New method `snapshotDraftIfDirty()`: writes the draft **unless** this navigation was
  an explicit, informed leave (§3) — otherwise identical to today's `mirrorDraft()`
  body.
* `save()`'s `STATES.SAVED` branch already calls `clearDraft(this.key)` — unchanged;
  a successfully-saved field has nothing left to snapshot at departure, and if `dirty`
  is `false` the `beforeunload` handler skips the write entirely (see below).

```js
snapshotDraftIfDirty() {
    if (!this.dirty || explicitLeaveRequested()) {
        return;
    }

    writeDraft(this.key, { value: this.fieldValue(), baseHash: this.baseHash, savedAt: Date.now() });
},
```

This mirrors `mirrorDraft()`'s existing shape exactly (same `{ value, baseHash,
savedAt }`) — no draft schema change, so `triageDraft()`'s three-way decision in
`store.js` needs no rework beyond the TTL check (§2).

## 2. TTL — a read-time check, no schema change

`savedAt` (`Date.now()` at write time) already exists in every draft. Add one check,
consulted before `triageDraft()` runs at all:

```js
// resources/js/autosave/store.js
export const DRAFT_TTL_MS = /* see open-questions.md — concrete number needed */;

export function isDraftExpired(draft, now = Date.now()) {
    return now - draft.savedAt > DRAFT_TTL_MS;
}
```

`field.js`'s `checkForDraft()` (`:344-363`) calls `isDraftExpired()` first — an expired
draft is `clearDraft()`'d and treated exactly like "no draft", never reaching
`triageDraft()`, never entering the modal's list. No existing three-way result
(`drop-silently`/`offer-restore`/`offer-compare-only`) changes shape; expiry is a
pre-filter in front of that logic, not a fourth outcome.

## 3. Suppressing the write on explicit leave

`data-loss-warnings` already dispatches `window.dispatchEvent(new CustomEvent
('autosave:explicit-leave'))` synchronously, immediately before assigning
`window.location.href` (`navigation-guard.js:73-76`) — dispatch (and every listener
it calls synchronously) completes before the navigation that triggers `beforeunload`
even begins.

Add a tiny, module-level flag in `field.js` (not the Alpine store — this is
transient per-navigation state, not shared UI state):

```js
let explicitLeavePending = false;
window.addEventListener('autosave:explicit-leave', () => { explicitLeavePending = true; });

function explicitLeaveRequested() {
    return explicitLeavePending;
}
```

Registered once at module load (top-level, not inside `registerAutosaveField()` — no
guard needed since ES module bodies only execute once regardless of how many times
`registerAutosaveField()` itself is called). Every field's `snapshotDraftIfDirty()`
consults the same flag — no per-field wiring needed, and no reset is needed either:
once true, the page is already navigating away, so there's no future `beforeunload` on
this same document instance to suppress incorrectly.

Native `beforeunload` (real tab-close, browser-quit, hard navigation) can never set
this flag — browsers withhold which button the user picked on that prompt from JS
entirely, a limitation `data-loss-warnings`' own architecture doc already established.
That path always calls `snapshotDraftIfDirty()` with `explicitLeaveRequested()` false,
i.e. always writes defensively — exactly `spec.md`'s stated non-goal boundary.

## 4. Page-level recovery modal

Replaces `autosave-field.blade.php`'s inline `data-autosave-draft-banner` (`:82-117`)
and `field.js`'s per-field `draftAction`/`draftValue`/`draftSavedAt`/`restoreDraft()`/
`discardDraft()` state, which move from being *rendered per field* to being *read by* a
new shared piece.

**`resources/js/autosave/draft-recovery.js`** (new file, mirrors `badge.js`'s
pure-logic-plus-Alpine-wrapper shape):

* A pure `collectDraftEntries(fields, elements)` — given the store's `fields`/
  `elements` maps (already populated by every mounted `autosaveField` component,
  `field.js:177-179`), returns `[{ key, action, value, savedAt, label }]` for every
  key with a non-expired, non-`drop-silently` draft. Reuses `readDraft()`/
  `isDraftExpired()`/`triageDraft()` (already exported from `field.js`/`store.js`) —
  no new storage-reading logic, just the existing per-field logic run once per
  registered key instead of once per component's own `init()`.
* `Alpine.data('draftRecoveryModal', ...)` — on `init()` (page load, after every
  `autosaveField` has registered), builds the list via `collectDraftEntries()`; if
  non-empty, `$dispatch('open-modal', 'draft-recovery')`. Exposes `restore(key)`,
  `discard(key)`, `restoreAll()`, `discardAll()`.
* `restore(key)`: looks up `Alpine.store('autosave').elements[key]` (the field's root
  `$el`, already tracked there since task 01 of `data-loss-warnings`) to reach that
  field's own `querySelector('textarea')`, sets its value, and dispatches a bubbling
  `input` event — the exact mechanic `field.js`'s current `restoreDraft()` (`:365-377`)
  already uses, just invoked from outside the field's own component instead of from
  within it. `clearDraft(key)` afterward.
* `discard(key)`: `clearDraft(key)` only.
* No implicit discard on close (Esc/backdrop) — `overview.md`'s acceptance
  criterion. The modal simply reappears next load if the writer dismissed it without
  choosing, since nothing was cleared.

**`resources/views/components/autosave-draft-recovery-modal.blade.php`** (new,
`<x-dialog>`-based, same family as `data-loss-warnings`' `delete-with-move-dialog` —
another "aggregate several entries into one modal with per-item actions plus a bulk
shortcut" component, useful precedent for shape/structure). Mounted **once**, globally,
in `layouts/app.blade.php` next to `<x-autosave-status-badge />` and the
`unsaved-changes-guard` dialog — not per-field, not per-page.

**`autosave-field.blade.php`**: remove the entire inline banner block (`:82-117`) and
its three `x-show` branches — the field component no longer renders its own recovery
UI. `field.js`'s `autosaveField()` factory correspondingly drops `draftAction`/
`draftValue`/`draftSavedAt`/`restoreDraft()`/`discardDraft()` and the `checkForDraft()`
call in `init()` — that per-field triage logic moves to `draft-recovery.js`'s
`collectDraftEntries()`, run once globally instead of once per field.
