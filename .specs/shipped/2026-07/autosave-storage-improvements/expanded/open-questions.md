---
title: Autosave Storage Improvements — Open Questions
---

# Open Questions

This is the `grilling` skill's agenda for `plan-tasks` — each question states a
recommended answer; the grill is where you confirm or override it.

1. **Exact TTL value.** `spec.md` says "a few hours (same-day, not indefinite)" but
   never a number. **Recommendation: 4 hours.** Long enough to survive a lunch break or
   a same-day return to the app, short enough that a "stranded draft from a different
   session" collision (the reseed bug, the two-tab-different-day scenario) becomes rare
   rather than designed-around. A calendar-day boundary (resets at midnight regardless
   of elapsed time) was considered and rejected — it makes the "leave at 11:58pm, no
   deadline pressure" case behave inconsistently for no real benefit over a flat
   duration.

2. **Does the recovery modal need its own Alpine store, or can it read
   `Alpine.store('autosave')` directly?** `architecture.md` §4 proposes reading the
   existing store's `fields`/`elements` maps directly (already populated by every
   mounted field) rather than adding a second store. **Recommendation: no new store** —
   `collectDraftEntries()` is a pure function taking those maps as arguments, callable
   from a plain `Alpine.data('draftRecoveryModal', ...)` with no new global state to
   keep in sync.

3. **One write per dirty field vs. a single aggregated `beforeunload` entry keyed by
   page.** Restated from `spec.md`'s own open question. **Recommendation: one write
   per dirty field** (§ `architecture.md` §1) — no new storage format, reuses the
   existing per-field key and `writeDraft()` call verbatim, and the "aggregation" the
   modal needs is a read-time concern (iterate registered keys) rather than a
   write-time one.

4. **Does removing the inline per-field banner break any existing PHP feature test?**
   Checked this session: `tests/Feature/AutosaveFieldComponentTest.php` has no
   assertion on `data-autosave-draft-banner`/`draftAction`/`restoreDraft` markup today
   — likely safe, but confirm at implementation time since a test elsewhere in the
   suite could still reference it.

5. **Where does `entry.compareUrl` come from for the modal**, since `autosave-field
   .blade.php` currently computes `$compareUrl` itself and that component is losing its
   recovery UI entirely? `ui.md` proposes a new `store.compareUrls[key]`, set in
   `autosaveField()`'s `init()` alongside the existing `store.elements[key]`.
   **Recommendation: yes** — smallest change that keeps `collectDraftEntries()` a pure
   function of the store's existing maps, no new route-resolution logic duplicated in
   `draft-recovery.js`.

6. **Should `explicitLeavePending` (architecture.md §3) ever be reset**, e.g. for an
   SPA-style page that *doesn't* actually navigate away after all? Not applicable here
   — this app has no client-side routing (confirmed during `data-loss-warnings`'
   grilling: no Livewire, every page is a full reload), so once `autosave:explicit-
   leave` fires, the current document is already being torn down; there is no future
   `beforeunload` on this same document instance to worry about incorrectly
   suppressing. **Recommendation: no reset needed** — flag intentionally at module
   scope, no cleanup required.
