# MCP server

[Documentation](../README.md) › [Development](README.md) › MCP server

The `avcso` MCP server lets Claude Code read a project and make small annotations. Code: `app/Mcp/`, `routes/ai.php`. Config: `.mcp.json`.

## How it runs

- Stdio only, started by `.mcp.json` inside the Docker dev container: `docker compose -f docker-compose.dev.yml exec -T -u laravel app php artisan mcp:start avcso`.
- It uses the Docker database (the `dbdata` volume), the one the running app shows. Never start it natively: a second writer on another database is a bug.
- Docker down: the tools fail to connect. Run `make up`, then `/mcp` in Claude Code to reconnect.
- Claude Code names the tools `mcp__avcso__<name>`.

## Tools

| Tool | Kind | Purpose |
|---|---|---|
| `list-projects` | read | Projects with counts |
| `project-outline` | read | Book, act, chapter, scene tree |
| `list` | read | Rows of one type: plotlines, events, notes, categories, codex types |
| `show` | read | All readable fields of one entity |
| `search` | read | The project search, as text |
| `scene-context` | read | A scene with its codex, notes and earlier scenes |
| `update-scene` | write | Title, description, status, event, viewpoint |
| `save-scene-review` | write | The scene's review note in the `TODO` category |
| `create-note`, `update-note` | write | Notes, with category and links |
| `create-codex-entry`, `update-codex-entry` | write | Characters, locations, organizations |
| `set-codex-attribute` | write | An attribute value from an event onward |

## Rules

- No prose. No tool writes `scenes.contents` or the book front and back matter. The author writes.
- No delete, no move, no `position`, no `plotlines.is_main`. No tool has the `IsDestructive` annotation.
- Output is Markdown text. IDs show as `#<id>`. Rich fields take and print HTML.
- Writes use the same services as the controllers and Form Request rules. Revisioned fields record a manual revision labelled "Claude", so the history shows the change and restores it.
- Every tool is listed by name in `.claude/settings.json`. A new tool needs a deliberate allow. `tests/Feature/Mcp/ToolSafetyTest.php` checks the list and the rules above.
- A tool wraps its body in `ReturnsToolErrors::guard()`. Without it, an exception becomes an opaque protocol error.

## Before a big batch

Nothing backs up automatically. Before a long run of writes, take a snapshot. See [database backups](database-backups.md).

```bash
docker compose -f docker-compose.dev.yml exec app php artisan db:backup --name=before-claude
```

## Read-only SQL

For data no tool shows, use `bash scripts/db.sh`. It opens the Docker database read-only. See `scripts/README.md`.

> [!WARNING]
> Tools act as the project owner, with no policy check. See [technical debt](technical-debt.md#mcp-tools-skip-authorization).
