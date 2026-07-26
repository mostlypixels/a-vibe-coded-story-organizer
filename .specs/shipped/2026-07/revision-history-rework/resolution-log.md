# Revision History Rework — resolution log

The running record of feedback/decisions, deviations from the spec/plan, and
issues → resolutions found while implementing and verifying this feature. The
`plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
before extending the feature.

## Feedback & decisions

* **2026-07-25 — The unit of the interface is the entity, not the field.** Raised by the
  user mid-planning: the final form moves from "revision by field" to "revision by entity
  with its fields". This reshaped compare as well as the list — compare now diffs two
  *save points* across every field (via snapshots), and a single field is a `?field=`
  filter on both pages rather than a route segment. `expanded/architecture.md` was
  rewritten around it.
* **2026-07-25 — The HTML-aware visual diff is built in-house.** Every off-the-shelf PHP
  option is GPL-2.0 (`caxy/php-htmldiff` v0.1.17, the only maintained one) or an
  unmaintained PHP 5.3-era fork; this app ships as source. Built instead on
  `jfcherng/php-sequence-matcher` (BSD-3, already a transitive dependency). Evaluation
  table in `expanded/diffing.md`.
* **2026-07-25 — "Undo this save" restores only the fields that save touched**, never a
  whole-entity rollback. A full "restore the entity to this point" would silently discard
  unrelated later edits to other fields; if it is ever wanted it is its own feature.
* **2026-07-25 — A base-hash conflict redirects back with an error alert** on both revert
  paths, instead of `abort(409)`'s bare error page. The 409 *status* survives only on the
  JSON autosave endpoint, where a client reads it. This changes behaviour asserted in
  `tests/Feature/RevertRevisionTest.php` — deliberately.
* **2026-07-25 — The history page gets a "manual saves only" filter**, sharing the
  server-side filter logic with the compare pickers.

## Deviations from the spec/plan

* **Legacy revision rows are deleted, not backfilled.**
  `notes/handoff-revision-compare-interface.md` §3.1 said existing rows stay null and
  permanently ungroupable; the first draft of this plan backfilled a unique ULID per legacy
  row instead. The owner's call (2026-07-25) is simpler still: the migration clears the
  table. A null grouping key poisons every read path (`GROUP BY save_id` collapses all
  legacy rows into one bogus group, and `COALESCE(save_id, 'row:' || id)` is not portable
  across the five engines), and a backfill only buys an era of rows with grouping but no
  summaries. Safe because the project is **pre-V1** and the only existing data is the
  Melusine demo/test seed. `RevisionRecorder::ensureBaseline()` re-seeds a baseline per
  field on its next write, so history restarts from a truthful starting point.
* **The N+1 boundary fetch changed purpose.** `notes/revision-compare-decisions.md`
  required fetching N+1 rows per page so row 21 could be diffed against its predecessor.
  With summaries stored at write time, no diffing happens on the list at all; the extra
  group is still fetched, but only to build the last row's "compare with previous" link.
* **Task 7's routing tests went into the existing `tests/Unit/Services/RevisionDifferTest.php`**
  rather than a second file (`tests/Unit/RevisionDifferRoutingTest.php`, as the task named
  it). One test class per class is the convention everywhere else in this suite, and the
  old file's cases *were* the routing cases — they only tested the branch that no longer
  exists. Two files would have meant two setUps and two sets of "which differ ran"
  assertions over the same class.
* **Task 8 needed a summary path for Markdown/Plain, which the task file only described
  for Rich.** It said "rendered in the renderer's `inline` mode", but `DiffHtmlRenderer`
  takes `DiffBlock[]` and a source field has no blocks. Rather than fabricate blocks or
  let the source path emit its own `<ins>`/`<del>`, both strategies now reduce to
  `DiffSpan[]` (a new `ChangeExcerpt` carries them plus the hunk count) and render through
  one new public `DiffHtmlRenderer::renderSpanRun()`. This also moved all `jfcherng` usage
  out of `RevisionDiffer` into a new `App\Services\Diff\SourceDiffer` — a genuine second
  caller, which is what the "no abstraction before reuse is real" rule was waiting for.
  `Scene.contents` — the field the writer cares about most — gets a real summary as a
  result.
* **The summary budget is spent outward from the change, not from the start of the
  excerpt.** Not stated either way in the plan, and it decides whether the feature works:
  a word changed 300 characters into a paragraph would otherwise be exactly the part that
  got cut, leaving the row showing an unchanged opening. A quarter of the budget goes to a
  run-up into the change, the rest follows it, and either cut end is marked with an
  ellipsis. Pinned by a test.
* **A formatting-only change has no marked words in its summary.** There is no marker for
  "reformatted" — `<ins>`/`<del>` would both be lies — so such a row shows the affected
  text unmarked and relies on `change_count`. The summarizer prefers a block whose *words*
  changed when there is one, so this only surfaces when formatting is genuinely all that
  moved. Task 12's `<x-diff>` is where an affordance for it would go.

* **`SaveEntry` carries no `compareWithPreviousUrl`; `SavePoint` carries a
  `previousSaveId` instead.** The sketch in `expanded/architecture.md` put a URL on the
  entry. Both the group's "compare with previous" action and an entry's "and N more
  changes" link resolve to the same `from`/`to` pair, so one id on the group covers both —
  and route names belong in the view rather than in a service, particularly since the
  route this would have named does not exist until task 14.
* **`SavePoint` gained a `lastRevisionId` the sketch did not have.** Task 11's snapshot
  bound is `(created_at, id)`, so a save point has to name its own id tie-break. It must
  come from the whole group rather than from `entries`, which a field filter may have
  narrowed — otherwise a filtered history page would resolve snapshots differently from an
  unfiltered one.

* **`DiffHtmlRenderer` writes the formatting-change note; `<x-diff>` only styles it.**
  Task 12 asked the component to render an `x-badge` naming what changed. It cannot: the
  component receives a *string* of HTML and never parses it, so anything that has to sit
  inside a particular block must be produced where the block is — exactly as the `sr-only`
  marker labels already are. The renderer gained a `MARK_NAMES` map and emits
  `<span class="diff-note">formatting changed: bold added</span>`; the component styles it
  to match `x-badge`'s `info` variant.
* **`<x-diff>`'s rules are plain CSS in `app.css`, not Tailwind utilities in the
  component.** Pseudo-element gutter glyphs and descendant selectors over markup the
  template never sees read far better as CSS, which is why `.tiptap` is already written
  that way. The component is still the only view that applies those classes, so "one place
  owns diff styling" holds.

* **Tasks 13 and 14 landed as one commit.** They are one route contract: task 13's rows
  link to compare and task 14 changes what compare accepts, so splitting them would have
  left a commit where the history page's own links 404 — and task 13's own tests require
  those links to *work*. Task 14 could not simply go first either, because its "Back to
  history" link needs the entity-level history route.
* **`SavePoint` numbering and the option label live in `x-revision-picker`, not in a
  shared partial.** Task 14 built a `save-point-option` partial for its two `<select>`s;
  task 15 folded it into the picker component, which serialises the option list once and
  feeds both its own halves — the baseline `<select>` and the combobox. One source, so the
  two can never label the same save differently.

* **Task 16 split the base-hash check out of the revert body, rather than moving the body
  verbatim.** The task said "moved verbatim in behaviour", and it is — but as
  `assertUnchanged()` + a private `restore()` instead of one method. Task 17 has to verify
  *every* field's hash **before** writing *any* of them (all-or-nothing), which is impossible
  if the check and the write are welded together. `revertField()` is still the whole
  sequence in one call for the single-field path.
* **The conflict is a `RevisionConflictException` caught in the controller, not a renderable
  exception.** Laravel would let the exception render its own redirect, which would spare
  both revert actions a `try`/`catch`. `ImportController` already establishes the local
  convention — catch the domain exception, `return back()->with(...)` — and an explicit catch
  is the reading a junior developer can follow without knowing that exceptions may carry a
  `render()` method. It also keeps task 17's `DB::transaction` rollback obvious at the call
  site.
* **The revert flash rendering went into `x-revisions-layout`, and the success message with
  it.** Not in the task, but the task's error alert had nowhere to appear: no view read
  `session('status')`, so task 11's "reverted" confirmation had never once been shown. Both
  outcomes render in the shared shell — see *Known gaps* below for the full note.

* **Task 17's "refuse the current save point" was overturned — the task contradicted
  itself.** It listed both *"the undo is itself one save point — symmetric with what it
  undoes, and immediately undoable in turn"* and *"reverting the current save point is
  refused"*. An undo **becomes** the newest save point the moment it is written, so under
  the second rule it can never be undone and the first is dead on arrival. The test caught
  it (`undoing the undo moves forward again` failed). The rule was inherited from per-field
  revert, where it is right: *Revert to this* on the newest revision restores the value
  already in the column, a real no-op. Undo runs the other way — it restores what came
  **before** — so undoing the newest save is "undo what I just saved", the most useful case
  there is. Owner's call (2026-07-25): **drop the refusal**; any save point can be undone.
* **A baseline save point cannot be undone.** Not in the task, and it needs to be: a
  baseline is the seeded pre-history value, so "the value before it" is nothing, and
  undoing one would silently empty the field. The button is hidden and the endpoint refuses
  it. (Undoing a *normal* save that created a field's content does still empty it — there
  the emptiness is a real earlier state the writer had, not an artefact of seeding.)
* **The undo form carries a base hash for every registered field, not only the group's.**
  The task said "one `base_hashes[<field>]` hidden input per field in the group". But
  `?field=` narrows `SavePoint::$entries`, and the undo must restore the **whole** save
  regardless of what the page is filtered to — so a filtered page would submit an
  incomplete set and the undo would refuse itself. The page already computes a hash per
  registered field for the compare screen's restore buttons; sending all of them costs
  three to six hidden inputs and removes the coupling entirely. The server resolves the
  group's real field list from the database and ignores the rest.
* **`restore()` now takes a value and a label rather than the `Revision` they came from.**
  The two callers reach them differently: a single-field revert restores *that* revision's
  value, while an undo restores the value the field held **before** the row the save wrote
  — a different row, and sometimes no row at all.
* **The `reverted-save` flash renders in `layouts/app.blade.php`, not in `x-edit-layout`.**
  Six of the seven edit forms use `x-edit-layout`; `codex/edit` does not. The app shell is
  the only place all seven meet. It is scoped to that one status value, so no other page's
  flash can surface through it.
* **The predecessor lookup is re-stated in `RevisionReverter`, not shared.**
  `RevisionSnapshot` and `RevisionRecorder` both already walk `(created_at, id)` backwards,
  but each wants a different thing out of the row it finds — a bound, a value, and now a
  `Revision`. Three ten-line queries reading the same way beat one parameterised helper
  that has to explain which of the three it is doing.

* **Task 18's History link is its own component, `x-entity-history-link`, not markup inside
  `x-edit-actions`.** The task assumed all seven revisionable edit screens route through the
  Actions card. **`plotlines/edit` does not** — it has no sidebar at all; its Save button and
  delete live inside the form card. Rather than restructure that page (which would silently
  hand it "Save and stay" and a different delete affordance — a change nobody asked for),
  the link became a one-line component that `x-edit-actions` renders when given a
  `historyModel`, and that `plotlines/edit` renders directly beside Save. Two real callers
  existed the moment the task started, so this is not abstraction ahead of need.
* **The link derives its URL slug from the model class**, via
  `AutosavableFields::slugFor($model::class)`, rather than taking the slug as a prop. Eight
  call sites hand-writing `"act"`/`"codex"`/… is eight chances to typo a slug into a 404 that
  only shows up when a writer clicks it; an unregistered model now throws at render time
  instead.
* **The sidebar's entity row is a link with the same active treatment as its field leaves.**
  The task asked only for the name to become a link. Left as a plain bold `<div>` turned
  anchor it would have been the one row in the tree that never shows where you are, since
  the entity-level page is exactly the page with no field selected. Its active test is
  therefore `$activeField === null` — the leaves' test with the field clause inverted.
* **Task 18's last bullet was already satisfied**, as the handoff predicted: tasks 13 and 14
  had already moved every three-argument `route('revisions.index', [... 'field' => ...])`
  call site to the `?field=` form. Nothing to change.
* **Task 18 was checked in a browser** (`/run-imagoldfish`), the first thing in this feature
  to be. The Actions-card button and the sidebar entity link were both confirmed rendering
  and navigating, with the entity row highlighted on arrival. It does **not** close
  `standing-issues.md` #4 — the alert paths still have not been seen.

* **Task 19's feature-level changelog section was not written — it would have been pure
  duplication.** The task specifies one dated `## YYYY-MM-DD — Revision history rework
  (#PR)` section covering the whole feature: the entity-level URLs and their redirects, the
  whole-save undo, the visual diff, the conflict-UX change, the removal of *"Formatting
  changed only."*, and a `Removed` line for the migration that clears the `revisions` table.
  Every one of those already ships in `CHANGELOG.md` under the PR that landed it — #38
  (save points + the `Removed` line), #39 (visual diff + the formatting dead end), #40
  (summaries), #41 (save-point reads), #42 (the new URLs + redirects), #43 (conflict UX),
  #44 (undo), #45 (entry points). The task predates the split of this plan into eight PRs,
  each of which carries its own dated entry per the repo's changelog convention. Restating
  them under one heading would have described the same work twice, in a file whose headings
  answer *when something shipped*. **What the bullet was really protecting** — that each of
  those things is findable in the changelog — was verified entry by entry instead. This PR
  gets an ordinary dated section for its own documentation work.
* **The architecture section was reworked, not rewritten.** By task 19 the section had
  already been kept in step by tasks 13–17: save points, snapshots, the conflict split and
  `revertSave` were all documented and accurate. What was genuinely missing or stale is what
  changed — the two-altitude opening, the routes table and `view`/`update` split, the
  in-house-differ rationale with its rejected candidates, the coalescing/`save_id` warning,
  the migration's "why is my history empty" note, the entry points, and one **false** claim
  (that the history page has "no separate field switcher" — it has had a field filter since
  task 13).
* **Two stale passages in `best-practices.md` were fixed in passing**: a note claiming Acts,
  Chapters and the Story overview have no feature tests, and a changelog description
  predating the dated per-PR convention. Both are in a file task 19 edits anyway, and both
  were documentation stating something untrue about the repo.

## Known gaps

**Moved to [`standing-issues.md`](standing-issues.md).** A gap that survives the task that
found it is not part of the *record of the work* — it is a fact about the code, and it needs
to be read by whoever extends the feature next rather than buried in a log that is otherwise
finished business. That file now holds both the accepted costs that used to be listed here
and the defects found reviewing task 16.

Only entries that were **closed** stay below, as part of the record:

* ~~**Nothing renders the revert flashes yet.**~~ `RevisionController::revert()` already
  returned `back()->with('status', 'reverted')`, but no view read that key — the success
  message had been dropped on the floor since task 11, and so there was no render site for
  task 16's conflict alert either. **Closed by task 16**: both alerts now live in
  `x-revisions-layout`, the one shell the history and compare pages share, so both revert
  entry points are covered once rather than per page. Recorded because it was found while
  starting task 16 and is called out nowhere in the plan.

## Issues → resolutions

_None yet._
