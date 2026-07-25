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

## Issues → resolutions

_None yet._
