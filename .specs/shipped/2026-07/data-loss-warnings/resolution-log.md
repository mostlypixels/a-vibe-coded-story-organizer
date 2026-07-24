# Data Loss Warnings — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and issues →
resolutions found while implementing and verifying this feature. The `plan-implementer`
agent appends here per task; `ship-plan` consolidates it. Read it before extending the
feature.

## Feedback & decisions

Resolved during the `plan-tasks` grilling session (before any task was implemented):

1. **`beforeunload` fallback is in scope**, not a pre-existing thing to leave alone.
   The original one-line draft assumed a native tab-close warning already existed;
   `grep -rn beforeunload resources/js` found nothing. Decision: add it — it's cheap,
   reuses the same `isDirty()` signal as the in-app guard, and its absence would have
   left the single most damaging silent-data-loss path (closing the tab) uncovered.
2. **Cascade-delete confirmation became a "move or delete" flow for Act/Chapter**,
   not just a smarter count string. Discovered during grilling that single-record
   reparenting already exists (`chapters/edit.blade.php`'s `act_id` picker,
   `scenes/edit.blade.php`'s `chapter_id` picker, both server-validated) — just not
   offered in bulk from the delete flow. User's framing: "if it's a parent like 'act',
   suggest moving the chapters to other act; if it's a chapter, propose moving the
   scenes to other chapter."
3. **Project keeps the plain cascade-count confirmation**, no reassign option — its
   direct children (acts, plotlines, events, codex entries) have no natural "other
   project" to move to. Scope explicitly excludes cross-project reassignment.
4. **Move + delete is one action**, not two steps — a single form submit reassigns
   children then deletes the now-empty parent in one request/transaction.
5. **Moved children are appended at `max(position) + 1`** in the destination, in their
   original relative order — not interleaved by any merge-ordering scheme (this app has
   no such concept to base one on).
6. **No valid destination → only "Delete everything" is offered**, never a blocked
   delete. An act/chapter with children but no sibling to move them to behaves like
   before this feature existed.
7. **Cascade counts: two levels for Act (chapters + scenes) and Project (its four
   direct collections, not walked further), one level for Chapter (scenes only, no
   grandchildren).**
8. **Project's multi-category message lists only non-zero categories** — a brand-new
   project (only its un-deletable main plotline) gets the original unqualified text,
   never "0 acts, 0 events…".
9. **Navigation guard mounts globally** in `layouts/app.blade.php`, same pattern as the
   existing autosave status badge — no per-page opt-in.
10. **`autosave:explicit-leave` is the entire integration surface** with the sibling
    `autosave-storage-improvements` spec (not yet built) — one agreed `window` custom
    event, no shared module or import dependency.
11. **V1 navigation-guard scope is autosave-field pages only** — general non-autosave
    dirty-form tracking is an explicit non-goal/follow-up, not this spec.

A concrete latent gap surfaced along the way, not a design decision but load-bearing
for tasks 04/05: `Chapter`/`Scene`'s `booted()` hook only auto-assigns `position` on
`creating`, never on a plain `act_id`/`chapter_id` update (`app/Models/Chapter.php:56-
60`) — today's single-record reassignment via the edit form's picker can already
silently collide two records' positions in the destination scope. Tasks 04/05 must set
`position` explicitly rather than relying on that hook; this existing gap itself is out
of scope to fix beyond what tasks 04/05 already need.

## Deviations from the spec/plan

* **Task 01**: the task file described extending `field.test.js` as matching "this
  codebase's convention [of] testing `registerAutosaveField()` against a mocked Alpine
  instance" — but no such mock existed there yet; every existing test in that file only
  covered the DOM-free exported functions (`storageKeyFor`, `shouldAutosave`,
  `readDraft`/`writeDraft`/`clearDraft`), same as `badge.test.js`'s stated convention of
  leaving the `Alpine.data()` wrapper itself to the manual checklist. Added a minimal
  local `createAlpineStub()` (just enough of `store()`/`data()` to invoke the plain-
  object component methods directly against a real jsdom `<div><textarea></div>`) to
  cover the new `dirty`/`isDirty()` behavior without pulling in the real Alpine runtime.
  Future tasks touching `field.js`'s Alpine-adapter behavior can reuse this stub instead
  of re-deriving it.

* **Task 02**: `ui.md`'s reference markup put `x-data="navigationGuard()"` directly on
  `<x-dialog>`. `resources/views/components/dialog.blade.php` does not merge
  `$attributes` onto its inner `<x-modal>` root (nothing in either
  `dialog.blade.php`/`modal.blade.php` does `{{ $attributes }}`), so an `x-data` given
  straight to `<x-dialog>` is silently dropped — Alpine never sees it, and
  `confirmLeave()`/`$dispatch` calls in the footer buttons throw "not defined". Fixed by
  wrapping the whole `<x-dialog name="unsaved-changes-guard">…</x-dialog>` block in a
  plain `<div x-data="navigationGuard()">` in `layouts/app.blade.php` instead — Alpine
  resolves undefined properties/methods through the parent scope chain, so
  `confirmLeave()` called from inside the nested `x-modal`/`x-dialog` scopes still
  resolves correctly. Verified end-to-end in a real browser (see Issues below) since a
  passing test suite would not have caught this — Alpine attribute errors are silent
  (logged to console but don't fail a click) and there is no Blade test for "does this
  attribute reach the DOM node Alpine binds to".

## Issues → resolutions

* **The `x-data` scoping bug above was only caught by manual browser verification**, not
  by `npm run test` or `composer test` — both suites were green with the bug still in
  place (the pure `shouldIntercept()` predicate and the store's `isDirty()` are both unit
  tested and both worked correctly in isolation; the break was purely in the Blade
  wiring between the two, which only a real click in a real DOM exercises). Root cause:
  see the deviation entry above. This reinforces the plan's own instruction to build
  frontend assets and drive the actual page rather than trusting a green PHPUnit/vitest
  run for anything touching Blade/Alpine wiring.
* **`wait-for text=...` in the browser driver is unreliable for this dialog** — the page
  already contains an unrelated hidden element whose text also matches "Unsaved changes"
  substring-wise (`field.js`'s own draft-restore banner, "Unsaved changes were found
  from your…"), and Playwright's `getByText(...).first()` picks whichever matches first
  in DOM order regardless of visibility. Worked around during verification with
  `button:visible:text-is("Cancel")`/`"Leave anyway")` (exact, visible-only) instead of
  substring text matches — worth remembering for any future manual verification of this
  dialog.

## Task 03 — Project delete confirmation

* **Deviation/gap filled**: neither the task file nor `architecture.md`/`ui.md` resolved
  what happens with the `events` and `plotlines` counts for a project that has *no*
  extra content — `Project::booted()` auto-creates a main plotline (`is_main: true`)
  and two `is_fixed` bookend events (Start/End) for *every* project, so a naive
  `loadCount(['plotlines', 'events'])` would always read `plotlines_count >= 1` and
  `events_count >= 2`, and a brand-new project would never hit the "all zero → fall
  back to the unqualified question" branch `testing.md` explicitly requires. Resolved
  by scoping those two counts to exclude the auto-created rows —
  `plotlines()->where('is_main', false)` and `events()->where('is_fixed', false)` — so
  "brand-new project" reads as zero across all four categories, consistent with how the
  task already treats the main plotline as invisible to the user. `acts`/`codexEntries`
  counts are unscoped (no auto-created rows in either).
* **Message built in the controller**, not the Blade view: `ProjectController::edit()`
  now returns a `deleteConfirm` string built by a new private
  `buildDeleteConfirmMessage()` helper, and `projects/edit.blade.php`'s
  `:delete-confirm` prop just passes `$deleteConfirm` through — matches
  `architecture.md`'s own comment placement ("`ProjectController::edit()` builds the
  sentence") over the task file's more ambiguous "either file" phrasing.
  `Illuminate\Support\Arr::join($nonZero, ', ', ' and ')` (Laravel 13) does the
  comma-plus-"and" join; each category string comes from `trans_choice()` using this
  app's existing inline-pattern convention (`resources/views/projects/show.blade.php`'s
  plotline/event count strings), not a new lang file (this app has none).
* **Issue caught during test-writing, not by the assertion itself failing for the
  right reason**: an early draft of the "non-zero categories" test asserted
  `assertDontSee('plotline')`/`assertDontSee('event')` to prove those words were absent
  from the confirm string — but `layouts/navigation.blade.php`'s nav menu always
  contains "Plotlines"/"Events" links, so the assertion failed for an unrelated reason
  (nav chrome, not the confirm string). Fixed by asserting the exact expected sentence
  via `assertSee(..., false)` plus `assertDontSee('Are you sure you want to delete this
  project?')` instead of the word-level negative checks — a reminder that
  `assertDontSee` on a common English word against a full rendered page (with app nav)
  is too broad a net in this app.

## Task 04 — Act delete: move or cascade

### Deviations from the spec/plan

* **Reusable component prop shape differs from `ui.md`'s sketch.** `ui.md`'s reference
  markup used a single `childLabel`/`childCount` pair and conflated the destination noun
  with the child noun (its `Delete everything (:count :label)` would read "3 act"). The
  built `delete-with-move-dialog` instead takes `childCount`/`childSingular`/
  `childPlural` (the moved children), a separate `destinationNoun` ("act"), and an
  OPTIONAL `secondaryCount`/`secondarySingular`/`secondaryPlural` folded only into the
  honest "delete everything" summary (an act's scenes). Pluralisation is done inside the
  component via this app's inline `trans_choice` pattern convention, so callers pass only
  counts + destinations. Task 05 (Chapter) reuses it by omitting the secondary props →
  one-level cascade, no markup duplication.
* **`ActController::index()` gained a `$destinationActs` query** (all sibling acts,
  ordered by position). The task said "nothing to add there", but the per-row move dialog
  needs a destination list that is independent of the current `search` filter — reusing
  the filtered `$acts` would offer a wrong/empty picker whenever a search is active.
* **`edit-actions` got an optional `delete` slot.** Rather than baking Act/Chapter dialog
  logic into the shared component, a passed `delete` slot replaces the default
  `confirm()` delete button while keeping the Actions card layout. Other entities are
  untouched. The dialog itself is rendered next to the trigger (sidebar on edit; after
  the table on index, since a modal `<div>` is invalid inside `<tbody>`).

### Issues → resolutions

* **`act_id` is NOT in `Chapter::$fillable`, so `$chapter->update(['act_id' => …])` was
  silently dropped** — the moved chapters stayed on the source act and were then
  cascade-deleted with it (a green-looking move that actually destroyed the children).
  Root cause: mass-assignment guarding; `Chapter::$fillable` is `name, description,
  cover_image, position` only. Fix: reparent through the relationship
  (`$chapter->act()->associate($destination); $chapter->save();`), the same pattern
  `ChapterController::update()` already uses and documents. Caught by the feature test
  `test_deleting_an_act_can_move_its_chapters_to_another_act` (asserted the moved chapter
  survived), not by any type/lint check — a plain `update()` with a non-fillable key is
  valid PHP and a valid Eloquent call, it just no-ops the guarded attribute.
* **The dialog trigger button needs `x-data=""`.** A bare `x-on:click="$dispatch('open-modal', …)"`
  on a button that sits outside any Alpine `x-data` scope is never initialised by Alpine,
  so the click did nothing (modal `show` never flipped). Fix: add `x-data=""` to the
  trigger (both edit and index), matching the established pattern in
  `admin/revisions/edit.blade.php`. Verified in-browser: after the fix,
  `Alpine.$data(modalRoot).show` flips to `true` on a real click.
* **The critical "don't move when Delete-everything is chosen" behaviour** relies on the
  `<select name="move_children_to">` being `x-bind:disabled="mode !== 'move'"` — a
  disabled control is not submitted, so choosing "delete" sends no `move_children_to` and
  the backend cascades. Verified in-browser by driving Alpine's `mode` to `delete` and
  confirming `select.disabled === true` / `required === false` (and the inverse in move
  mode). Without the `disabled` bind, a hidden-but-enabled select would still submit its
  value and silently turn every "delete everything" into a move.

### Verification note (headless driver limitation)

* **`x-dialog`/`x-modal` does not visually render in the `run-imagoldfish` headless
  driver even when `show === true`** — computed `display` stays `none` and the screenshot
  shows no overlay. Confirmed this is environmental, not a bug in this dialog, by
  reproducing the exact same `show=true` + `display:none` against the app's pre-existing,
  known-good admin **Revisions → Delete all** modal (same `x-dialog` + `x-data=""`
  pattern). So the runtime surface was verified via: DOM assertions (select + options +
  radios present), `Alpine.$data().show` flipping on real click, and the select
  `disabled`/`required` toggle — not via a visual screenshot of the open modal. Also
  note `wait-for text=Move` is a false-positive here (the edit page's helper text "Use
  the move up/down buttons…" contains "move"), and `wait-for <css-selector>` passes on
  DOM attachment, not visibility — use `Alpine.$data`/`getComputedStyle` or exact,
  visible-only selectors when verifying these modals.

## Task 05 — Chapter delete: move or cascade

* **No component change needed.** The `delete-with-move-dialog` component built in task
  04 was reused unchanged — task 05 passes Chapter/Scene props (`child-singular="scene"`,
  `destination-noun="chapter"`, no `secondary-*` props → one-level cascade "Delete
  everything (N scenes)"). The generalization task 04 built (optional secondary
  grandchildren) paid off exactly as intended; no markup was duplicated or edited.

### Issues → resolutions

* **`chapter_id` is NOT in `Scene::$fillable`** (only `name, description, contents,
  notes, status, position, event_id`), the same latent gap task 04 flagged for
  `Chapter::act_id`. A plain `$scene->update(['chapter_id' => …])` would have silently
  no-op'd, leaving the scenes on the source chapter to be cascade-deleted with it.
  Reparented through the relationship instead — `$scene->chapter()->associate($destination);
  $scene->save();` — mirroring `SceneController::update()`. Guarded by
  `test_deleting_a_chapter_can_move_its_scenes_to_another_chapter` (asserts the moved
  scenes survive and point at the destination).

### Verification note

* Runtime surface verified by server-rendering `chapters/edit.blade.php` (via the
  controller `edit()` action with a shared empty `ViewErrorBag`, since tinker has no
  session error-bag middleware) and confirming the assembled markup: the trigger's
  `x-on:click="$dispatch('open-modal', 'delete-chapter-<id>')"` matches the dialog's
  `open-modal.window` listener for the same name, the "Move N scenes to another chapter"
  option, the `name="move_children_to"` select with the sibling chapter as an `<option>`,
  and the honest "Delete everything (N scenes)" line all present. The interactive
  Alpine behaviour (show-flip on click, select `disabled`/`required` toggle) was not
  re-driven in-browser because the component and modal are byte-identical to the
  task-04 Act dialog already browser-verified there — only the props differ.
  Feature tests also assert these markers over real HTTP (`assertSee 'name="move_children_to"'`,
  the sibling chapter name, the "This will also delete … N scenes" delete-only branch).
