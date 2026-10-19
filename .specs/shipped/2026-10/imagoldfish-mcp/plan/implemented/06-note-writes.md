# 06 — Note writes

## Scope

- `create-note`: `project`, `title`, `body` (HTML), optional `category` (ID or name), optional `links` (list of `type:id`, the `?link=scene:12` format from `documentation/features/notes.md`).
- `update-note`: `note`, optional `title`, `body`, `category`, `links` (replaces the link set when sent).
- Follow the `NoteController` and `NoteLinkController` write paths.
- Deferred: note category tools (second plan).

## Depends on

- 02.

## Key decisions already made

- A category name that does not exist fails validation. No tool creates categories in this plan.
- The `body` revision is manual, label `__('Claude')`.

## Docs

- `documentation/features/notes.md` → Model, Links.
- `expanded/architecture.md` → Rules for every write tool.

## Tests

- Create with links to a scene and a codex entry. The links show on both sides.
- A link target from another project fails validation.
- Update `body` records one "Claude" revision.
- `<script>` in `body` is stored sanitized.
