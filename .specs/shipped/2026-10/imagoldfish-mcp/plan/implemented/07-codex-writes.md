# 07 — Codex writes

## Scope

- `create-codex-entry`: `project`, `type` (`CodexEntryType`), `name`, optional `description` (HTML), optional `aliases`.
- `update-codex-entry`: `entry`, optional `name`, `description`, `aliases` (replaces the alias set when sent).
- `set-codex-attribute`: `entry`, `attribute` (name), `value`, optional `start_event` (default: the project Start event). Follow the `CodexAttributeValueController` and `CodexEntryAttributeController` paths.
- All through `CodexEntrySaver` or the services the controllers use. No media input.
- Deferred: codex media, attribute definitions (second plan).

## Depends on

- 02.

## Key decisions already made

- `CodexEntrySaver` needs a `CodexMediaUploads`. Pass an empty one. Do not add a media-free method unless the empty object does not work.
- The type cannot change on update.
- Attribute values follow the leading-anchor invariant in `documentation/features/codex.md`. Use the existing code path; do not reimplement it.
- An attribute name that does not exist for the type fails validation. No tool creates attribute definitions.

## Docs

- `documentation/features/codex.md` → Temporal attributes, Scene references.
- `expanded/architecture.md` → Rules for every write tool.

## Tests

- Create an entry. Scenes that mention its name gain the reference.
- A new alias resyncs references. An unchanged alias set does not rescan (assert with a spy or query count).
- `set-codex-attribute` at a later event adds a timeline value. The leading anchor stays valid.
- A description change records one "Claude" revision.
