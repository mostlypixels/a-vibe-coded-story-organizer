# Task 16 — Extract `RevisionReverter`, and fix the conflict UX

## Scope

`App\Services\RevisionReverter::revertField(Model $entity, Revision $revision, string $baseHash, User $user): string`
— today's `RevisionController::revert()` body, moved verbatim in behaviour:

1. compare `$baseHash` against `hash('sha256', current value)`;
2. re-validate the old value against **today's** rules
   (`AutosavableFields::validationRule()`) — an older revision must never bypass rules
   tightened since it was recorded;
3. assign + `save()` so mutators run (e.g. `SanitizesRichHtml`);
4. record a new `origin: revert` revision of the *stored* (post-mutator) value, labelled
   "Reverted to :date";
5. return the field name.

`RevisionController::revert()` becomes resolve → authorize → delegate → redirect.

**One behaviour change** (grill decision 10): a base-hash mismatch no longer `abort(409)`s
into a bare error page. Both revert paths **redirect back with an error alert** —
*"This changed somewhere else since you opened this page — reload and try again."* The 409
*status* stays only on the JSON autosave endpoint (`FieldAutosaveController`), where a
client reads it. Update `tests/Feature/RevertRevisionTest.php` accordingly, and say why in
`resolution-log.md`.

Does **not** add the whole-save path (task 17) — but design the signature for it.

## Depends on

Task 2.

## Key decisions already made

* Revert stays **additive**: a new row, never a rewrite. Reverting twice in a row just
  moves forward again.
* The base-hash check is the concurrency answer — a revert against state that moved must
  never silently clobber it.
* Re-validating the old value is not optional: `AutosavableFields::validationRule()` is
  the single source, and rules can have tightened.

## Consult

* `expanded/architecture.md` — *`App\Services\RevisionReverter`*.
* `app/Http/Controllers/RevisionController.php` (`revert()`) — the code being extracted.
* `expanded/open-questions.md` Q6 — the conflict-UX decision and its cost.

## Tests

`tests/Feature/RevertRevisionTest.php` (updated, not replaced):

* every existing assertion still holds (owner reverts, new `revert` row written, value
  restored, non-owner 403, revert-twice works, mutators run);
* **changed**: a base-hash mismatch now redirects back with an error in the session
  instead of returning 409, and nothing is written;
* the autosave endpoint still returns **409 JSON** on the same conflict (regression guard
  that the two paths were deliberately split).
