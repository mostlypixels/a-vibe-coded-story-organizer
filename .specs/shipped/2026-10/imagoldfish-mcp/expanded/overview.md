# Overview

## User stories

- As Claude, I read a project outline with IDs, so I can find a scene without SQL joins.
- As Claude, I write a scene summary to `scenes.description`, and the History tab shows it.
- As `scene-reviewer`, I save my findings with one tool call, with no shell quoting.
- As Claude, I create a codex entry or set an attribute value at an event, after a read of a scene.
- As the author, I allow the read tools once and approve each write tool by name.
- As the author, I know no tool changes my prose.

## Acceptance criteria

- `laravel/mcp` (v1.x, supports Laravel 13) is installed. A local server is registered in `routes/ai.php`.
- `.mcp.json` starts the server through `docker compose -f docker-compose.dev.yml exec -T -u laravel app php artisan mcp:start imagoldfish`.
- The read tools and write tools in [architecture.md](architecture.md#tool-catalog) exist with explicit names.
- No tool input or output schema has a `contents` field for writes. Book `dedication`, `acknowledgements`, `preface`, `postface` are not writable.
- No tool deletes or reorders.
- Each write uses the service or model path that the matching controller uses. Rich HTML goes through `SanitizesRichHtml`.
- A write to a revisioned field records a manual revision with the label "Claude". The History tab shows that label.
- Invalid input returns a tool error with the validation messages. Nothing is saved.
- An unknown ID returns a tool error, not an exception trace.
- `scene-reviewer` uses the MCP tools. Its docs and `documentation/development/scene-review-agent.md` say so.
- `.claude/settings.json` allows the read tools. It no longer allows `Bash(sqlite3 *)`.
- `documentation/development/mcp-server.md` exists and the development index links it.

## Conflicts with existing rules

- **Authorization.** `CLAUDE.md` says every controller action authorizes through `ProjectPolicy`. An MCP tool has no request user. The server is local stdio only, so the tools act as the project owner (`$project->user`), the same as `SceneReviewNoteWriter`. Record this in `documentation/development/technical-debt.md`, with the fix for multi-user work: an authenticated HTTP transport and a policy check per tool.
- **Main plotline.** No tool writes `plotlines.is_main`. The main plotline invariant stays with the model and the existing UI.
- **Position ordering.** Create tools append through the model hooks (`max(position) + 1`). No tool sets `position`.
- **Native runtime.** The server always runs in Docker. A native run would be a second writer on a different database, see `CLAUDE.md`.
