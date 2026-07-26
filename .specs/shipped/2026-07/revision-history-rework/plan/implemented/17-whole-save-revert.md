# Task 17 — Undo a whole save

## Scope

**Route**: `POST /revisions/saves/{save}/revert` → `revisions.saves.revert`, `{save}`
constrained to the ULID alphabet (`[0-9A-HJKMNP-TV-Z]{26}`) so a malformed id 404s at the
router.

**Controller** `RevisionController@revertSave`: load the group
(`Revision::where('save_id', $save)->get()`, 404 when empty), take its entity, authorize
`update` on `$entity->revisionProject()` — the ULID is a lookup key, never a capability —
validate `base_hashes` (an array, one per field), delegate, redirect to the entity's edit
form with a `reverted-save` flash naming the restored fields.

**Service** `RevisionReverter::revertSave(Model $entity, Collection $group, array $baseHashes, User $user): array`:

* wrap everything in `DB::transaction`;
* verify **every** field's base hash *before* writing anything — all-or-nothing, because a
  half-applied undo is worse than none;
* `$recorder->startNewSave($entity)` so the reverted rows form **one new save point**;
* per field, delegate to the same `revertField()` logic (task 16) so the two paths cannot
  drift;
* return the restored field names.

**UI**: fill the slot task 13 left in `revisions/partials/save-point.blade.php` — a POST
form with one `base_hashes[<field>]` hidden input per field in the group, a confirm
("Undo this save? Every field it changed goes back to its previous value. Nothing is
deleted — the undo is recorded as a new save."), hidden on the current save point. Plus
the `reverted-save` flash rendering.

## Depends on

Tasks 13, 16.

## Key decisions already made

* **Only the fields that save touched** — never a whole-entity rollback (grill decision 9).
  Unrelated later edits to other fields survive.
* **All-or-nothing**: one transaction, every hash checked up front.
* **The undo is itself one save point** — symmetric with what it undoes, and immediately
  undoable in turn.
* Conflict → redirect back with an error alert (task 16's decision), not a bare 409 page.
* Reverting the *current* save point is refused (no button; the POST rejects it too).

## Consult

* `expanded/architecture.md` — `@revertSave`, `RevisionReverter::revertSave()`.
* `expanded/ui.md` — the row actions and the flash wording.
* `expanded/overview.md` — the Revert acceptance criteria.

## Tests

`tests/Feature/RevertSaveTest.php` (new):

* owner undoes a save: every field it touched returns to its previous value, *n* new
  `origin: revert` rows are written **sharing one new `save_id`**;
* a field the save did **not** touch is untouched by the undo (the scope promise);
* **non-owner gets 403**; a guest is redirected to login;
* a base-hash mismatch on **any** field → nothing written (assert revision count and every
  field value unchanged) and a redirect with an error;
* the redirect lands on the entity's edit form with the `reverted-save` flash naming the
  restored fields;
* undoing twice in a row is legal and moves forward again;
* an old value that no longer passes today's validation fails cleanly without storing;
* undoing the current save point is refused;
* an unknown or malformed `{save}` 404s.
