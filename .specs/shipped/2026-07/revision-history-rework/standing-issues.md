# Revision History Rework — standing issues

**What is still true of the shipped code.** Read this before extending the feature.

Every defect review found in this feature was fixed (seven, all on 2026-07-26, shipped in
PRs #47 and #60), each with a regression test verified to fail before its fix. Their
entries lived here until then; the record is in git history and in `resolution-log.md`.

What remains are **accepted costs**: consequences of decisions taken with eyes open. They
are not bugs and not a to-do list. Do not "fix" one without re-opening the decision it came
from — each says which.

Distinct from `resolution-log.md` on purpose. That file is the *record of the work* and its
entries stop being actionable once a task is done. Everything here is **still true of the
code on `master`**.

> [!NOTE]
> Every screen of this feature has been driven in a browser (2026-07-27, `/run-imagoldfish`):
> the history list, the compare screen as #59 rebuilt it, both revert-conflict alerts, and
> the undo flow. The conflict alerts need a stale base hash, which no amount of clicking
> produces — reach them by overwriting the form's hidden `base_hash` before submitting.

---

## Accepted costs

Moved here from `resolution-log.md` → *Known gaps*, which is where they were first recorded.

### The source diff has two accessibility channels, not three

`expanded/ui.md` requires tint + glyph + a visually-hidden label on every marked passage.
`jfcherng` writes its own `<ins>`/`<del>` and offers no hook for a label, so the
Markdown/plain side gets the tint and the glyph plus the semantic elements. Closing it means
the source path emitting its own markers rather than delegating to the library — a
task-7-sized change, not a styling one. Documented in `documentation/architecture.md`.

### A coalescing autosave narrows what *Undo this save* covers

Accepted when Q1 was settled (`expanded/open-questions.md` Q1): a coalescing row keeps its
*original* `save_id`, exactly as it already keeps its original `created_at`. So a save
touching three fields where one coalesces into a still-open burst from earlier puts that
field in the **earlier** save point — the group the writer thinks of as "the save I just
made" lists two fields, and task 17's *Undo this save* then silently leaves the third alone.
The alternative (rewriting an existing group's membership after the fact) is worse. Also
documented in `RevisionRecorder::record()`'s docblock and in `documentation/architecture.md`.

### A prune leaves stale summaries behind

Accepted when Q7 was settled: deleting an `automatic` row leaves its successor's stored
`summary_html` / `change_count` describing a diff against a row that no longer exists, so the
**history list** can under-report what a save changed. The compare screen always computes
live and is unaffected. Recomputing during a mass prune would turn a cheap `DELETE` into an
O(n) diff job, which is why it was not taken. Documented in `expanded/data-model.md`'s
warning callout.
