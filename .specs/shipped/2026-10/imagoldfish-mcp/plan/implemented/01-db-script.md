# 01 — Read-only SQL script

## Scope

- `scripts/db.sh "<sql>"`: runs `sqlite3 -readonly -header -column /var/lib/imagoldfish/database.sqlite` in the Docker `app` container.
- Sets `MSYS_NO_PATHCONV=1`, so Git Bash keeps the container path.
- Fails with "Docker is not running. Run `make up`." when the container is down.
- Add it to `scripts/README.md`.
- `.claude/settings.json`: add `Bash(bash scripts/db.sh *)`, remove `Bash(sqlite3 *)`.
- Deferred: the MCP allow rules (08).

## Depends on

- None.

## Key decisions already made

- Read-only always. No flag turns writes on.
- Docker database only. No native fallback.

## Docs

- `expanded/architecture.md` → Permissions.

## Tests

- `shellcheck scripts/db.sh` passes.
- A feature test, like `DockerComposeDevTest`, checks that the script passes `-readonly` and the `dbdata` path.
