---
status: shipped
shipped: 2026-10-18
planned: 2026-10-18
expanded: 2026-10-18
---

# Imagoldfish MCP

## Problem

Claude and the project agents reach the data through shell commands: raw `sqlite3`, or
artisan through `docker compose exec`. The auto-mode check blocks some of these calls, Git
Bash changes container paths, and a prefix allow rule cannot limit what a command writes.
Agents still need to write. `scene-reviewer` saves notes, and Claude writes scene
summaries. An MCP server gives them a fixed set of typed tools that the app validates.

## Goals

- A local MCP server that runs inside the Docker app, started over stdio by Claude Code.
- Read and write tools for these entities: projects, books, acts, chapters, scenes,
  plotlines, notes and note categories, events, and codex entries (characters, locations,
  organizations, with their aliases and attributes).
- A search tool over the same domains as `scene:search` (`SearchDomain`, `SearchMode`).
- A project outline tool: the book, act, chapter and scene tree, with IDs.
- One tool for each job, so each permission rule names one job.
- Writes go through the existing services and models, so `booted()` hooks, revisions,
  word counts and entity links stay correct.
- The `scene:*` artisan commands and the MCP tools share one implementation.
- `scene-reviewer` moves to the MCP tools.

## Non-goals

- No tool writes scene prose (`contents`). Prose is the author's work.
- No raw SQL tool, no tinker tool.
- No HTTP transport, no auth tokens, no use by more than one person. Those wait for multi-user work.
- No public REST API.
- No delete tools in the first version.
- No access to settings, users, imports, exports, or media files.

## Rough approach

- Use the official `laravel/mcp` package. First check that it supports Laravel 13.
- Register the server in the project `.mcp.json`. The launch command is
  `docker compose -f docker-compose.dev.yml exec -T -u laravel app php artisan mcp:start …`.
- Tools call the existing services (`ProjectSearch`, `SceneReviewNoteWriter`,
  `CodexEntrySaver`, `SceneSaver`, …). Move logic out of the artisan commands where needed.
- Validate tool input with the same rules as the Form Requests.
- Allow-list the read tools in `.claude/settings.json`. Write tools ask until the user allows them.

## Open questions

- Which fields can each write tool change? For example, scene description, status, viewpoint
  and event; codex attributes on the timeline.
- Do MCP writes record a revision? Is a new `RevisionOrigin` case, such as "agent", needed?
- Does a write batch take a backup first (`BackupDatabase`), or is the revision history enough?
- Does a refused write on Gardenia Girl need a guard in the server, or are the missing prose tools enough?
- Which user do the tools act as, for ownership and authorization?
- How does the server behave when Docker is down: does it fail fast with "run `make up`"?
- What is the context cost of the tool definitions? Is the cost low enough with deferred loading?
