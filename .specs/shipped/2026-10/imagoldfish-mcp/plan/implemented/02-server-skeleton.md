# 02 — Server skeleton

## Scope

- `composer require laravel/mcp` (v1.x). Record it in `documentation/development/dependencies.md`.
- `routes/ai.php`: `Mcp::local('imagoldfish', ImagoldfishServer::class)`.
- `app/Mcp/Servers/ImagoldfishServer.php`: name, version, instructions (no prose writes; run `make up` if down).
- `.mcp.json`: server `imagoldfish`, command `docker compose -f docker-compose.dev.yml exec -T -u laravel app php artisan mcp:start imagoldfish`.
- `list-projects` tool: ID, name, book count, scene count, word count.
- A shared way to turn `ModelNotFoundException` and `ValidationException` into `Response::error()`. Use a small base trait or class in `app/Mcp/Tools/Concerns/`.
- Deferred: other read tools (04), writes (05–07), allow list (08).

## Depends on

- None.

## Key decisions already made

- Stdio only. No HTTP route, no auth.
- Markdown text output.

## Docs

- `expanded/architecture.md` → Layout, Docker down.
- `expanded/overview.md` → Acceptance criteria.

## Tests

- `tests/Feature/Mcp/ListProjectsToolTest.php`: lists projects with counts.
- A test reads `.mcp.json` and checks the server name and the Docker command.
- A test proves an unknown ID turns into a tool error (use a throwaway tool in the test, or wait for 04 and test there).
- Manual check: `/mcp` in Claude Code shows `imagoldfish` connected. Record the result in the resolution log only if it fails.
