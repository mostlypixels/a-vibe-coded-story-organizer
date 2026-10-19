# Imagoldfish MCP — plan

## Execution order

| Task | Purpose |
|---|---|
| 01-db-script | `scripts/db.sh`: read-only SQL on the Docker database; replaces the `sqlite3 *` allow rule |
| 02-server-skeleton | `laravel/mcp`, server, `.mcp.json`, `list-projects`, error handling |
| 03-shared-scene-services | Move scene resolving and review-context text out of the `scene:*` commands |
| 04-read-tools | `project-outline`, `list`, `show`, `search`, `scene-context` |
| 05-scene-writes | Narrow scene save method, `update-scene`, `save-scene-review` |
| 06-note-writes | `create-note`, `update-note` |
| 07-codex-writes | `create-codex-entry`, `update-codex-entry`, `set-codex-attribute` |
| 08-permissions-reviewer-docs | Allow list, `scene-reviewer` switch, docs, technical debt |

## Binding decisions

- The grill answers in `../resolution-log.md` → Feedback & decisions override `expanded/`.
- Only the 7 write tools listed there. The other write rows in `expanded/architecture.md` → Tool catalog are out of scope.
- Tools act as the project owner (`$project->user`). No request user, no policy call.
- Rich fields (descriptions, note body) take HTML. The model sanitizer (`SanitizesRichHtml`) cleans it.
- Revisioned field writes: `RevisionRecorder::recordManualChanges()`, label `__('Claude')`, user = project owner.
- Output is Markdown text. IDs show as `#<id>`. Errors use `Response::error()`.
- Every tool sets `$name` explicitly. Names are kebab-case and match `expanded/architecture.md`.
- Server name: `imagoldfish`. Claude Code tool names: `mcp__imagoldfish__<name>`.

## Invariants every task preserves

- No tool writes `scenes.contents`, book front or back matter, `plotlines.is_main` or any `position`.
- No tool deletes or moves. No tool has the `IsDestructive` annotation.
- Writes go through the same service or model path as the matching controller, in one transaction.
- Validation reuses the Form Request rules. Autosaved fields use the `AutosavableFields` registry rule.
- The server runs in Docker only. Nothing starts it natively.
