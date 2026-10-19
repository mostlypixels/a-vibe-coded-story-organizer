# 08 — Permissions, reviewer, docs

## Scope

- `.claude/settings.json`: allow every `mcp__imagoldfish__<name>` read and write tool by name. No wildcard, so a new tool needs a deliberate allow.
- `.claude/agents/scene-reviewer.md`: use `scene-context`, `show`, `search` and `save-scene-review`. Add them to `tools:`. Keep the `scene:*` commands as the fallback when the server is down.
- `documentation/development/mcp-server.md`: what the server is, how it starts, the tool list, the no-prose rule, Docker down (`make up`, then `/mcp`), manual `BackupDatabase` before a big batch. Link it from `documentation/development/README.md`.
- `documentation/development/scene-review-agent.md`: point at the MCP tools.
- `documentation/development/technical-debt.md`: tools act as the project owner with no policy check. The fix for multi-user work: authenticated HTTP transport, a policy check per tool.
- `CLAUDE.md`: one line. Use the MCP tools first for project data, `scripts/db.sh` for other reads.
- `CHANGELOG.md`: one dated section.

## Depends on

- 01, 04, 05, 06, 07.

## Key decisions already made

- All 7 write tools are allowed by default.
- The `scene:*` commands stay.

## Docs

- `expanded/overview.md` → Conflicts with existing rules.
- `.claude/rules/documentation.md`, `.claude/rules/changelog.md`.

## Tests

- A test lists the server tools and checks each name is in the `.claude/settings.json` allow list.
- A test checks no registered tool has `IsDestructive` and no input schema has `contents`, `dedication`, `acknowledgements`, `preface`, `postface`, `is_main` or `position`.
- The documentation link test passes.
