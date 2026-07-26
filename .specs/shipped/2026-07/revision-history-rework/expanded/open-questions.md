# Open questions — Revision History Rework

Each carries a **recommended answer** already written into the other expanded docs. The
grilling step before `plan-tasks` should confirm or overturn them; overturning one means
editing the named doc, not just this file.

> [!NOTE]
> **Grilled 2026-07-25 — all recommendations confirmed.** Q4 was settled by the user
> directly ("the final form moves from revision *by field* to revision *by entity with its
> fields*"); Q5, Q6, Q8 and Q11 were confirmed as recommended in the grill round. The
> decisions are restated as binding in `../plan/00-overview.md` and recorded in
> `../resolution-log.md`. Nothing below is still open — this file is now the *why* behind
> the plan's binding decisions, not a to-do list.

---

### Q1 — A coalescing autosave: keep the original `save_id`, or take the new one?

`RevisionRecorder` overwrites a still-open `automatic` row within the field's window.
That row already belongs to an earlier save point.

**Recommended: keep the original.** The row keeps its original `created_at` today for
exactly the same reason — it is one continuing editing burst, and rewriting either field
would make the row claim to be something it is not. The cost is a real, accepted one: a
save touching three fields where one coalesces into an older burst produces a save point
listing two fields, and *Undo this save* then leaves the third alone. The alternative
silently rewrites an existing group's membership after the fact, which is worse.
→ `data-model.md` → *Coalescing keeps the original `save_id`*.

### Q2 — Legacy rows: null `save_id`, or one unique id each?

The handoff said "existing rows stay null, permanently ungroupable".

Originally recommended: backfill one unique ULID per legacy row (a group of one), which
removes the null branch from every read path and avoids `COALESCE(save_id, 'row:' || id)`,
whose string concatenation is not portable across the five supported engines.

**Settled 2026-07-25 — neither: the migration deletes the legacy rows.** Simpler than a
chunked backfill, and it leaves no era of rows that have grouping but no summaries. Safe
because the project is pre-V1 and the only existing data is the Melusine demo/test seed.
→ `data-model.md` → *Legacy rows are deleted, not backfilled*.

### Q3 — Does the field-scoped history survive as its own page?

**Recommended: no — it becomes `?field=` on the entity page**, with the two legacy URLs
kept as redirects (bookmarks, the sidebar, and the current documentation all point at
them). Two pages for one concept means two controllers, two views and two test suites.
→ `architecture.md` → *Routes*.

### Q4 — What does compare compare: two revisions of one field, or two save points? *(settled mid-session — confirm)*

**Recommended, and now assumed throughout: two save points of the entity**, each resolved
into a *snapshot* (for every field, the newest revision at or before that moment), with
one diff section per field that differs. This is what "revision by entity with its fields"
means on the compare screen; per-field compare becomes `?field=`.

Consequence worth confirming: a field **neither** save wrote can still appear in the diff,
because something changed it in between. That is correct for a snapshot comparison and
surprising if you expect "the diff of that save".
→ `architecture.md` → *Snapshots*, `overview.md` AC.

### Q5 — Does *Undo this save* restore only the fields the save touched, or roll the whole entity back to that snapshot?

**Recommended: only the fields it touched.** "Undo this save" is a promise about that
save. A full "restore the entity to this point" is a different, larger action (it would
silently discard unrelated later edits to other fields) and belongs in its own feature if
it is ever wanted. If the grill wants it now, it is cheap — `RevisionReverter` already
takes a field list — but it needs its own confirm wording and its own tests.

### Q6 — Base-hash conflict: raw 409, or redirect back with an error?

Today `RevisionController::revert` calls `abort(409)`, which shows a bare error page to a
writer who did nothing wrong.

**Recommended: for both revert paths, redirect back with an error alert** ("This changed
somewhere else since you opened this page — reload and try again"), and keep the 409
*status* only on the JSON autosave endpoint where a client reads it. This changes an
existing, tested behaviour (`RevertRevisionTest`), so it must be an explicit decision, not
a drive-by.

### Q7 — Stale summaries after a prune: accept, or recompute?

A pruned `automatic` row leaves its successor's stored summary describing a diff against a
row that no longer exists.

**Recommended: accept and document.** Recomputing during a mass prune turns a cheap
`DELETE` into an O(n) diff job, and the compare screen always computes live, so only the
list excerpt can under-report.
→ `data-model.md` → warning callout.

### Q8 — The HTML-aware differ: in-house, or vendor a GPL library?

`caxy/php-htmldiff` is the maintained option and is **GPL-2.0**; the two alternatives are
unmaintained PHP 5.3-era GPL forks. This app ships as source.

**Recommended: in-house**, built on `jfcherng/php-sequence-matcher` (BSD-3-Clause, already
in `vendor/` as a transitive dependency of the differ we use). ~4 small classes, no new
dependency, no licence question. The evaluation table and the fallback ladder are in
`diffing.md`.

### Q9 — Who sets a revision's `label`, and when?

Unchanged and out of scope: auto-generated on manual save (`RevisionRecorder::
manualSaveLabel()`) and on revert. A save point shows the first non-null label among its
rows. **Recommended: no user-facing label editor in this feature** — it is a nice feature
and an independent one.

### Q10 — Defaults to confirm

* `history.per_page = 20` save points.
* `summary.max_length = 200` rendered characters.
* `diff.max_word_complexity = 2_000_000` (|old tokens| × |new tokens|).
* Option-label format: `#<n> · <date> · <label> · <origin>` + **Current** marker.
* Date format `d F Y H:i`, matching the rest of the revisions UI.

### Q11 — Does the history list get the save-type filter too, or only the pickers?

The decisions notes left this open. **Recommended: yes, one shared filter control** — the
entity history already gets a field filter and a label search; a *manual saves only*
checkbox alongside them is the same GET-parameter pattern and answers the most common
question ("show me my real saves, not the autosave noise"). It reuses the picker's filter
logic on the server side.
