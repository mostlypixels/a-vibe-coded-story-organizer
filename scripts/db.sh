#!/usr/bin/env bash
# db.sh — run one read-only SQL statement on the Docker dev database.
# The database lives on the dbdata volume, so the host cannot open it. The
# -readonly flag is always on; no option turns writes on.
#
# Usage: scripts/db.sh "<sql>"
#
# Callers: .claude/settings.json (Bash(bash scripts/db.sh *)), humans and agents
set -euo pipefail

if [ "$#" -ne 1 ] || [ -z "$1" ]; then
    echo "Usage: bash scripts/db.sh \"<sql>\"" >&2
    exit 2
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

# Git Bash on Windows would rewrite the container path as a host path.
export MSYS_NO_PATHCONV=1

COMPOSE=(docker compose -f docker-compose.dev.yml)
DB_PATH="/var/lib/imagoldfish/database.sqlite"

if [ -z "$("${COMPOSE[@]}" ps --status running -q app 2>/dev/null)" ]; then
    echo "Docker is not running. Run \`make up\`." >&2
    exit 1
fi

exec "${COMPOSE[@]}" exec -T app sqlite3 -readonly -header -column "$DB_PATH" "$1"
