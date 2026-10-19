# 05 — Scene writes

## Scope

- A narrow save method on `SceneSaver` for the scene details. It never reads or writes `contents`:

  ```php
  /** @param array{name?: string, description?: ?string, status?: string, event_id?: ?int, viewpoint_character_id?: ?int} $details */
  public function updateDetails(Scene $scene, array $details, User $user, string $label): void
  ```

- `update-scene` tool: `scene`, optional `name`, `description` (HTML), `status`, `event`, `viewpoint`. Only the sent fields change.
- `save-scene-review` tool: `scene`, `body` (HTML). Calls `SceneReviewNoteWriter::write()`.
- Deferred: notes (06), codex (07).

## Depends on

- 03, 04.

## Key decisions already made

- Revision: manual, label `__('Claude')`, user = project owner.
- Validation: the matching rules of `UpdateSceneRequest`. The event and viewpoint must belong to the scene's project.
- No base hash. A tool write makes an open editor tab hit the existing 409 choice.

## Docs

- `expanded/architecture.md` → Rules for every write tool, and the `SceneSaver::update()` warning.
- `documentation/features/revisions.md` → Recording.

## Tests

- `update-scene` with only `description` leaves `contents`, `word_count` and the other fields unchanged.
- One manual revision labelled "Claude" for a changed description. None for an unchanged value.
- An event or viewpoint from another project fails validation.
- `save-scene-review` creates, then replaces, the review note.
- The input schemas have no `contents` field.
