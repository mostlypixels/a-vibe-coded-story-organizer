# Task 9 — The recorder stores summaries

## Scope

`App\Services\RevisionRecorder`:

* on **insert**, resolve the row's predecessor — the newest revision for the same
  `(revisionable_type, revisionable_id, field)` strictly older than the row being written
  — and store `summary_html` / `change_count` from `RevisionSummarizer`;
* on a **coalescing update**, recompute both columns (the row's `value` changed, so its
  summary is stale) — the predecessor is the row *before* the coalesced row, not the
  coalesced row itself;
* `ensureBaseline()` stores `null` / `0`.

`App\Services\Import\ProjectGraphImporter::importRevisions()`: summaries are **recomputed**
during replay, never read from the archive. Sidecars are ordered oldest-first and the
entity is newly created, so each row's predecessor already exists when its summary is
computed — keep the replay in that order and add a comment saying why it matters.

Field kind comes from `AutosavableFields::kindOf(AutosavableFields::slugFor($entity::class), $field)`.

## Depends on

Tasks 2, 8.

## Key decisions already made

* **The predecessor query costs one extra `value` read per write.** Accepted: the write
  path already touches that row's neighbourhood, and it buys a list page that never diffs.
* **Stale summaries after a prune are accepted and documented** (`expanded/data-model.md`
  warning callout): a pruned predecessor leaves its successor's stored summary
  under-reporting. Recomputing during a mass prune would turn a cheap `DELETE` into an
  O(n) diff job, and compare always computes live.
* Summary failure must never break a save: wrap the summarizer call so an unexpected
  exception logs and stores `null`/`0` rather than losing the writer's text. A revision
  without a summary is a cosmetic problem; a lost save is not.

## Consult

* `expanded/data-model.md` — *Who writes `summary_html` / `change_count`*, and the prune
  warning.
* `app/Services/RevisionRecorder.php` — the insert and coalescing branches.

## Tests

`tests/Feature/RevisionSummaryTest.php` (new):

* a recorded change stores a `summary_html` containing `<ins>`/`<del>` and a matching
  `change_count`;
* a coalescing autosave **refreshes** both columns (assert the value before and after the
  second PATCH);
* a baseline row stores null / 0;
* a 40-hunk change stores a bounded summary and `change_count = 40`, and the history page
  later renders "and 39 more changes" (assert the count here, the rendering in task 13);
* stored summary HTML is escaped — a value containing `<script>` round-trips as text;
* imported rows get recomputed summaries, in replay order (extend the import test);
* a summarizer that throws does not prevent the revision being written (fake/mock it).
