# Task 5 — The autosave response carries the authoritative count

## Scope

`FieldAutosaveController::update()` returns `word_count` in its JSON, read from the model
**after** `$model->save()` (so the hook from task 4 has run and the number is the stored
one).

* Present on every autosave response, not only for `contents` — a uniform response shape is
  cheaper for `field.js` than a conditional key. For a non-scene field it is that entity's
  count of the saved field, computed with `WordCounter` on the fly.
* No new endpoint, no route change, no authorization change.

## Why this is not optional

The JS counter is **indicative**: it does not strip fenced blocks and does not apply the
non-word rule. So the live number and the stored number legitimately differ — a scene
containing a fenced block reads high while typing.

This response is how they reconcile. The counter snaps to the authoritative number on every
save, so "indicative" means "briefly approximate", not "wrong". Without it the two just
drift apart and the writer stops trusting both.

## Depends on

Task 4.

## Key decisions already made

* Read the count **after** `save()`, never compute it from the request body — the server
  hashes and counts what it stored, never what a client sent (the same rule the base-hash
  machinery already follows).
* The browser never writes `word_count`. It is display-only on the client.

## Consult

`../expanded/open-questions.md` Q3, `../expanded/architecture.md`.

## Tests

Extend `tests/Feature/FieldAutosaveTest.php`:

* The response JSON contains `word_count` matching the stored value.
* Autosaving `contents` **containing a fenced code block** returns the count with the fence
  excluded — i.e. the response carries the authoritative number, not a naive split. This is
  the test that pins the reconciliation contract.
* A non-scene field (e.g. act `description`) also returns a `word_count`.
* The existing response keys (`hash`, `revision_id`, status) are unchanged — this task adds
  a key, it does not reshape the payload.
