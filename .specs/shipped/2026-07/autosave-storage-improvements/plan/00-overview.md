---
title: Autosave Storage Improvements — Plan Overview
---

# Plan Overview

Manual. Never itself implemented or moved to `plan/implemented/`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-write-once-with-ttl.md` | Stop mirroring on every keystroke; write the draft once at `beforeunload`, gate reads on a 4-hour TTL, suppress the write on an explicit-leave. No UI change — the existing inline per-field banner keeps working against the new write timing/TTL. |
| 02 | `02-draft-recovery-module.md` | New `resources/js/autosave/draft-recovery.js`: the pure `collectDraftEntries()` + `Alpine.data('draftRecoveryModal')`, plus `store.compareUrls[key]` wiring. Depends on 01 (`isDraftExpired`/`writeDraft` timing). |
| 03 | `03-recovery-modal-ui.md` | New page-level modal component, mounted globally; removes the old inline per-field banner (markup + `field.js` state) it replaces. Depends on 02. |

Each task is independently testable — 01/02 are JS-unit-verifiable with no UI change
required; 03 is the only task touching Blade/rendered UI and needs the manual
browser-verification step `data-loss-warnings` already established a precedent for
(twice: `x-dialog` silently dropping `x-data` when placed directly on it, and a
trigger button needing its own `x-data=""` to be Alpine-initialised — both only
caught by driving a real browser, not by a green test suite).

## Binding design decisions (do not re-litigate)

All resolved via grilling — full record in `../resolution-log.md`.

1. **TTL is 4 hours, a flat duration** — does not reset at midnight. A draft written
   at 11:58pm still has ~4 hours of life, not 2 minutes.
2. **One `localStorage` write per dirty field**, at `beforeunload`, not a single
   aggregated per-page entry. Same key format (`entity:id:field` /
   `new:entity:parentId:field`), same `writeDraft()` call as today — only the *when*
   moves, from `onInput()` to `beforeunload`.
3. **No new Alpine store.** The recovery modal reads `Alpine.store('autosave')`'s
   existing `fields`/`elements` maps (plus the new `compareUrls` map, decision 4)
   directly — `collectDraftEntries()` is a pure function over them, not a second
   source of truth.
4. **`store.compareUrls[key]`** is a new map, populated in `autosaveField()`'s
   `init()` alongside the existing `store.elements[key]` — the modal never
   recomputes a `revisions.compare` route URL in JS; it's pre-computed Blade-side
   exactly once, same as today's per-field banner already does before this change.
5. **`explicitLeavePending` never resets.** This app has no client-side routing —
   every page is a full reload — so once `autosave:explicit-leave` fires, the
   document is already unloading; there's no future `beforeunload` on the same page
   instance to worry about incorrectly suppressing.
6. **Expiry is a read-time pre-filter, not a new triage outcome.** The existing
   three-way `triageDraft()` result (`drop-silently`/`offer-restore`/
   `offer-compare-only`) is unchanged in shape; an expired draft never reaches it at
   all — treated identically to "no draft".
7. **The inline per-field banner is deleted, not adapted** — the field component
   renders nothing for draft recovery once task 03 lands; that UI (and its backing
   state — `draftAction`/`draftValue`/`draftSavedAt`/`restoreDraft()`/
   `discardDraft()`/`checkForDraft()` in `field.js`) moves to the new global modal.
8. **Closing the modal (Esc/backdrop) never implicitly discards** — only an explicit
   Restore/Discard click or TTL expiry removes a draft.

## Core invariants every task must preserve

* **The draft shape stays `{ value, baseHash, savedAt }`** — no schema change. TTL is
  computed from the existing `savedAt`, not a new field.
* **The three-way triage contract stays load-bearing.** `store.js`'s `triageDraft()`
  (`drop-silently`/`offer-restore`/`offer-compare-only`) is exercised by existing
  tests today and must keep passing unchanged — a base-hash mismatch must never offer
  a bare Restore, regardless of which task touches the surrounding code.
* **Native `beforeunload` (real tab-close) always writes defensively.** It can never
  observe `autosave:explicit-leave` (browsers withhold which button the user picked
  on that prompt from JS) — only the in-app navigation-guard path can suppress a
  write, and only for the specific navigation the writer explicitly confirmed leaving.
* **`data-loss-warnings`' existing integration surface is exactly one event name**
  (`autosave:explicit-leave`, already dispatched in `resources/js/navigation-guard.js`)
  — no task should add a second coupling point between the two features.
