# 04 — Backlinks index

## Scope

- Migration and model `EntityLink`, with the columns in `expanded/data-model.md` → `entity_links` table.
- `App\Services\EntityLinkIndex`:

  ```php
  public function sync(Model $source): void;   // every link field of the source
  public function forget(Model $model): void;  // rows where the model is source or target
  public function syncProject(Project $project): void;
  ```

- `App\Models\Concerns\HasEntityLinks` on every model in `RichTextFields::FIELDS` and on `Scene`:
  - `saved`: sync when a link field `wasChanged()`.
  - `deleted`: forget.
- `EntityLink::deleteFor()` in the Book, Act and Chapter `deleting` hooks, beside `Notable::deleteFor()`.
- Add one link to a Melusine seed note. The seeder calls `EntityLinkIndex::syncProject()` directly.
- Update `documentation/features/notes.md` → Deleting a linked entity, and its cascade warning.
- Deferred: the card that reads the index → 09; import sync → 05.

## Depends on

- 02.

## Key decisions already made

- A model hook, not a controller call: the index comes from the row's own fields.
- No foreign key on source or target. `project_id` cascades.
- Rows stay for missing targets until the target's delete hook removes them.

## Docs

- `expanded/data-model.md`. `expanded/architecture.md` → Index sync.

## Tests

- Save with two links → two rows. Edit to one → one row. Delete the source → none. Delete the target → none.
- Every save path: form update, `FieldAutosaver`, `SceneSaver`, `CodexEntrySaver`, revision revert, scene and codex duplicators.
- Cascade: delete a chapter → rows for its scenes, as source and as target, are gone.
- Seeder: the seed link has its row.
