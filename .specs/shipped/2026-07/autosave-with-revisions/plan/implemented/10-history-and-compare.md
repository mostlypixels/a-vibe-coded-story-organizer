# Task 10 — History + compare view

## Scope

* `App\Http\Controllers\RevisionController::index()` — `GET /revisions/{entity}/{id}/
  {field}`, listing date/author/label/origin badge/current-value marker, with a
  portable `LIKE` label search (mirroring `ProjectSearch`'s pattern, read this session).
  **Selects explicit columns, never hydrates `value`.** A field switcher listing the
  entity's other registered fields (from `AutosavableFields::REGISTRY[$entity]`).
  Baseline rows render as "Baseline — value before revision history", not a normal edit
  row.
* `RevisionController::compare()` — `GET /revisions/{entity}/{id}/{field}/compare?
  from=&to=`.
* `App\Services\RevisionDiffer` — **first sub-step: verify `jfcherng/php-diff`'s
  Laravel 13 / PHP 8.5 compatibility** (check Packagist/its repo for a maintained
  release; try `composer require` in isolation). If it works, wrap it. If it's
  unmaintained/incompatible, implement a small hand-rolled word-level diff (LCS over
  whitespace-tokenized arrays) instead — either way, `RevisionDiffer` is the only class
  the rest of the app calls, so this choice is fully contained here.
* Rich fields diff `RichText::toPlainText()` output (existing helper, read this
  session); Markdown/plain fields diff the raw stored text. When two revisions' plain-
  text projections are equal but raw values differ, render "formatting changed only"
  instead of an empty diff.

Does **not** include: the revert action (task 11 — the compare/history views only link
to it), the routes registration beyond `revisions.index`/`revisions.compare` (task 11
adds `revisions.revert`), or any purge/retention UI (tasks 12–13).

## Depends on

Task 6 (revisions must actually be created by real saves to have something to list —
though tests here can seed `Revision` rows directly via factory without going through
the controller).

## Key decisions already made

* **List queries never hydrate `value`** — this is the entire point of `size_bytes`
  existing (task 1); a code-review-level rule this task must actually honor, not just
  document.
* **Route naming**: `revisions.index` / `revisions.compare`, under the same
  `->whereIn('entity', AutosavableFields::slugs())` group as `autosave.update`
  (`handoff.md` §9.3's route table).
* **Compare diffs the *projection*, not raw HTML always** — rejected explicitly in
  `handoff.md` §5.3 as producing unreadable tag-soup for this audience of amateur
  writers.

## Consult

* `expanded/ui.md` — "History page", "Compare view" sections.
* `expanded/architecture.md` — `RevisionDiffer` sketch, routes block.
* `handoff.md` §5.1, §5.3, §6 (the unverified-library warning this task resolves),
  §9.2 (baseline row rendering), §9.3 (route table).
* `app/Support/RichText.php` (already read this session) — `toPlainText()` is the exact
  reduction to diff against for rich fields.
* `app/Services/ProjectSearch.php` (already read this session) — the portable `LIKE`
  search pattern to mirror for label search.

## Tests

* History index lists revisions newest-first (or whatever order the UI spec implies —
  confirm against `ui.md`), label search filters correctly, response never includes the
  full `value` payload (assert via response content or by asserting the underlying
  query's selected columns).
* Field switcher links to the correct sibling routes for a multi-field entity (e.g.
  `Scene` — `description`, `notes`, `contents`).
* A `baseline`-origin revision renders with the "Baseline — value before revision
  history" label, not a normal author/date row.
* Compare between two revisions with a real prose change (rich field) shows the actual
  diff.
* Compare between two revisions whose only difference is HTML wrapper tags (same
  `RichText::toPlainText()` output) shows "formatting changed only", not an empty diff.
* Compare on a `Markdown`/`Plain` field diffs raw text directly (no plain-text
  projection step).
* Non-owner gets 403 on both `index` and `compare`.
