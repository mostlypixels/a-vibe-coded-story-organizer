# Task 11 — Revert action

## Scope

* `RevisionController::revert(Revision $revision)` — `POST /revisions/{revision}/
  revert`, route name `revisions.revert`.
* Copies the target revision's `value` onto the live column, runs the **same**
  sanitization/validation the normal save path uses (via `AutosavableFields::
  validationRule()`/the model's mutators — reverting a rich field must still pass
  through `SanitizesRichHtml`), takes the same base-hash 409 conflict check as the PATCH
  endpoint (via a hidden form field carrying the current hash), and records a **new**
  `origin: revert` revision labeled `"Reverted to {original revision's date, formatted}"`.
* A revert button on both the history row and the compare view (Blade), behind the
  existing `x-dialog` confirm component.

Does **not** include any change to history/compare listing logic (task 10) beyond
adding the button; does not touch `RevisionRecorder` (already supports `origin: revert`
as a non-coalescing origin from task 4).

## Depends on

Task 10 (history/compare views exist to link from), task 4 (`RevisionRecorder`), task 6
(the conflict-check pattern to mirror).

## Key decisions already made

* **Revert is additive, never destructive** — the reverted-away-from state remains in
  history untouched; only a new row is ever added. No user action ever deletes history
  here (`handoff.md` §5.2 — this is a hard invariant, not a style preference: "a mis-
  click permanently destroys work" was the explicitly rejected alternative).
* **Revert does not load the value into the live editor for the writer to then save** —
  it commits immediately server-side (the alternative was rejected in `handoff.md` §5.2
  as "interacts badly with autosave, which commits it two seconds later anyway").
* **The label is auto-generated** ("Reverted to 14 July 09:12" style), not
  writer-entered — matches `handoff.md`'s example text.
* **Same conflict check as the PATCH endpoint** — a revert against stale state must
  also 409, not silently overwrite newer work that happened after the page loaded.

## Consult

* `expanded/architecture.md` — `RevisionController::revert()` code sketch.
* `handoff.md` §5.2.
* Whatever existing controller in this codebase already uses `x-dialog` for a
  destructive-feeling confirm (e.g. a delete action) — match its Blade wiring pattern
  exactly.

## Tests

* Reverting to an older revision updates the live column to that revision's value and
  creates a new `origin: revert` row — `revisions` count increases by exactly one, no
  existing row is modified or deleted.
* The new revert row's `label` matches the expected auto-generated format.
* Reverting a rich field re-runs sanitization (seed a revision containing a tag not in
  the current `RichTextFields::ALLOWED_TAGS`, if plausible, or simply assert the mutator
  path is exercised — confirm the stored value equals `cleanRichHtml($revision->value)`).
* A stale base hash on the revert request → 409, live column unchanged, no new revision
  row created.
* Non-owner gets 403.
* Reverting twice in a row (undo the revert by reverting again) works and both are
  visible in history — the "undo a revert by reverting again" case from `handoff.md`
  §5.2.
