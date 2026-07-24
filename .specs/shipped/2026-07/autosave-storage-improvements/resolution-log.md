# Autosave Storage Improvements — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues →
resolutions found while implementing and verifying this feature. The `plan-implementer`
agent appends here per task; `ship-plan` consolidates it. Read it before extending the
feature.

## Feedback & decisions

Resolved during the `plan-tasks` grilling session (before any task was implemented) —
all five substantive questions confirmed the recommendation as-stated, nothing
overridden:

1. **TTL is 4 hours, a flat duration** — not calendar-day. A draft written at
   11:58pm keeps its full ~4 hours, it doesn't reset at midnight.
2. **No new Alpine store.** The recovery modal reads `Alpine.store('autosave')`'s
   existing `fields`/`elements` maps (plus the new `compareUrls` map, decision 4)
   directly via a pure `collectDraftEntries()` function — not a second source of
   truth to keep in sync.
3. **One `localStorage` write per dirty field**, at `beforeunload` — not a single
   aggregated per-page entry. Reuses the exact existing per-field key format and
   `writeDraft()` call; only the call site moves from `onInput()`.
4. **`store.compareUrls[key]`**, populated in `autosaveField()`'s `init()` alongside
   the existing `store.elements[key]` — keeps `collectDraftEntries()` a pure function
   of the store's maps rather than recomputing a `revisions.compare` route URL in JS.
5. **`explicitLeavePending` never resets** — this app has no client-side routing
   (confirmed during `data-loss-warnings`' own grilling too), so once
   `autosave:explicit-leave` fires, the document is already unloading.

One item flagged as "confirm at implementation time" rather than a design decision:
whether removing the inline per-field banner breaks any existing PHP feature test.
Checked this session: `tests/Feature/AutosaveFieldComponentTest.php` has no assertion
on `data-autosave-draft-banner`/`draftAction`/`restoreDraft` markup today — likely
safe, but task 03 should re-check once its diff exists rather than assume.

## Deviations from the spec/plan

### Task 03 — removed `field.test.js`'s now-obsolete `checkForDraft()` test instead of adapting it

The task file only mentioned extending `draft-recovery.test.js` or adding a small
addition for the Alpine-wrapper half; it didn't call out `field.test.js`'s existing
`'checkForDraft() clears an expired draft and does not offer it for recovery'` test,
which asserted `field.draftAction` after `mountField()` — both `checkForDraft()` and
`draftAction` are removed from `field.js` by this task's own scope, so the test no
longer compiles against reality. Deleted it outright rather than adapting it: the exact
behavior it covered (an expired draft is excluded, even though the field's own
`init()` no longer performs that check) is already covered by
`draft-recovery.test.js`'s `'consults isDraftExpired() before triageDraft()'` test from
task 02, just at the new call site (`collectDraftEntries()`) instead of the old one.

### Task 01 — `mirrorDraft()` kept as a separate method from `snapshotDraftIfDirty()`

The task file's wording ("New method `snapshotDraftIfDirty()`: same body as today's
`mirrorDraft()`, guarded by...") could be read as *replacing* `mirrorDraft()`. It
wasn't — `onKeydown()`'s Ctrl-S handler on a create form (no `config.id` yet) still
calls `mirrorDraft()` directly, unguarded by the dirty/explicit-leave checks, since
Ctrl-S is itself evidence of intent to save right now. `snapshotDraftIfDirty()` is a
thin wrapper added alongside it (`if (!this.dirty || explicitLeaveRequested()) return;
this.mirrorDraft();`), wired only to the new `beforeunload` listener. This matches
architecture.md §1's own snippet, which independently writes the draft rather than
literally reusing `mirrorDraft()`, so no behavior changed — just confirming the two
call sites (Ctrl-S on a create form, and `beforeunload`) intentionally stayed separate
methods.

## Issues → resolutions

### Task 02 — `collectDraftEntries()` kept fully side-effect-free (no `clearDraft()` for expired/`drop-silently` drafts)

The task file and architecture.md don't explicitly say whether `collectDraftEntries()`
itself should evict expired/`drop-silently` drafts from `localStorage` while building
the list, the way `field.js`'s existing `checkForDraft()` does for its own key.
Decision 3 in `00-overview.md` calls it "a pure function" over the store's maps, so it
was implemented with zero `localStorage` writes — filtering (skip) only, never
clearing. A stale/expired entry simply stays in storage until either the writer
explicitly discards it via the modal (task 03) or the existing `evictOldestDraft()`
quota-pressure path reclaims it; it can never resurface in the recovery list once
expired, so there is no user-visible difference. Worth confirming task 03 doesn't
assume `collectDraftEntries()` already cleared these out from under it.

### Task 02 — `store.compareUrls[key]` defaults to `null`, not omitted, when `config.compareUrl` is absent

`autosave-field.blade.php` doesn't pass `compareUrl` into `x-data="autosaveField(...)"`
yet (that wiring is task 03's job once the Blade template needs it) — every field
mounted before task 03 lands gets `store.compareUrls[key] = null` via `config.compareUrl
?? null`. Confirmed this is inert for now (nothing reads the map yet outside
`draft-recovery.js`'s own module, not mounted anywhere) and exactly matches the task
file's own wording ("`config.compareUrl ?? null`").

### Task 03 — `draftRecoveryModal.init()` dispatching `open-modal` synchronously was a silent no-op

Root cause: Alpine initializes a component tree top-down (parent directives/`init()`
before descending into children). `<x-autosave-draft-recovery-modal>` wraps `<x-dialog>`
(→ `<x-modal>`) in `<div x-data="draftRecoveryModal()">`, exactly per this task's own
"wrap in a plain div" guidance — but that guidance only covered the `$attributes`-drop
trap, not this second, independent timing trap: dispatching `open-modal` from
`draftRecoveryModal()`'s own `init()` fires *before* Alpine has walked down into the
nested `<x-modal>` and wired up its `x-on:open-modal.window` listener, so the event is
dispatched into a vacuum — no error, no console warning, the modal element simply never
receives `show = true`. `composer test`/`npm run test` never touch real Alpine timing
(this file's DOM-free-logic convention deliberately stops short of mounting the real
Alpine runtime), so this was invisible until the `run-imagoldfish` browser check: the
modal never appeared on a page reload with a live draft, verified by inspecting the
screenshot and DOM, not just by the driver script reporting `ok`. Fixed by deferring the
dispatch one tick: `this.$nextTick(() => this.$dispatch('open-modal', 'draft-recovery'))`
in `resources/js/autosave/draft-recovery.js`'s `init()`. Worth remembering for any future
Alpine component that needs to dispatch an event *at mount* (not from a later user
action) into a sibling/descendant's own `.window` listener — `$nextTick()` is the fix,
not just wrapping the `x-data` placement.

### Task 01 — cross-test `beforeunload` listener leakage in `field.test.js`

None of the existing `mountField()`-based tests in this file ever call
`field.destroy()`, so every mounted field's `beforeunload` listener stays registered
on `window` for the rest of the test run. The new write-once tests all dispatch a real
`beforeunload` event; the first version reused `id: 42` (matching an existing describe
block's convention) across several of the new tests, so a still-live listener from an
earlier test's field re-wrote its own (identical) draft key when a later test's
`beforeunload` fired — a false pass/fail depending on order, not caught by the type
system or by reading the diff, only by running the suite. Fixed by giving every new
test its own unique `id` (1–5), so a leaked listener can only ever touch a key no
other test asserts on. `composer test`/`npm run test` don't catch cross-test
`window`-listener leakage on their own — worth remembering if task 02/03 add more
`window`-level listeners in tests.
