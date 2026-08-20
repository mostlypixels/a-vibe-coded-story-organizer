#!/usr/bin/env bash
# serve-app.sh — start the imagoldfish dev server (php artisan serve) in the
# background, refusing to start unless scripts/assets-state.sh passes (stale
# public/hot, missing build, dev database behind migrations). Records the
# server PID in scripts/.serve-app.pid and polls the URL until it answers.
# Idempotent: exits 0 if the recorded PID is already a live server. Stop the
# server with scripts/stop-app.sh.
#
# Usage: scripts/serve-app.sh [--port N]   (default port: 8000)
#
# Callers: .claude/skills/run-imagoldfish/SKILL.md
set -euo pipefail

usage() {
    echo "usage: scripts/serve-app.sh [--port N]" >&2
    exit 2
}

PORT=8000
while [ $# -gt 0 ]; do
    case "$1" in
        --port)
            [ $# -ge 2 ] || usage
            PORT="$2"
            shift 2
            ;;
        *) usage ;;
    esac
done
case "$PORT" in
    ''|*[!0-9]*) usage ;;
esac

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

PID_FILE="scripts/.serve-app.pid"
LOG_FILE="storage/logs/artisan-serve.log"
URL="http://localhost:$PORT"

# Idempotency: if the PID file points at a live process, the server is
# already running — nothing to do.
if [ -f "$PID_FILE" ]; then
    EXISTING_PID="$(cat "$PID_FILE")"
    if [ -n "$EXISTING_PID" ] && kill -0 "$EXISTING_PID" 2>/dev/null; then
        echo "Server already running at $URL (PID $EXISTING_PID) — nothing to do."
        exit 0
    fi
    # Stale PID file (process is gone) — clean it up and start fresh.
    rm -f "$PID_FILE"
fi

# The Docker stack (docker-compose.dev.yml) publishes the same port and writes
# the same database/database.sqlite. Two servers on one SQLite file across the
# WSL2 bind mount corrupt it, so refuse the port instead of competing for it.
if curl -sf "$URL" -o /dev/null --max-time 5; then
    echo "ERROR: something already answers at $URL — refusing to start a second server." >&2
    if docker ps --filter "publish=$PORT" --format '{{.Names}}' 2>/dev/null | grep -q .; then
        echo "       It is the Docker dev stack. Stop it first: make down" >&2
    else
        echo "       Stop that server first, or pick another port: --port N" >&2
    fi
    exit 1
fi

# Stale public/hot, a missing build, a dev database behind migrations: three
# states that serve broken pages while the test suite stays green.
if ! bash scripts/assets-state.sh; then
    echo "ERROR: refusing to start — the checks above must pass first." >&2
    exit 1
fi

php artisan serve --port="$PORT" > "$LOG_FILE" 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > "$PID_FILE"

# Poll until the server answers (up to ~30s).
ELAPSED=0
while [ "$ELAPSED" -lt 30 ]; do
    if curl -sf "$URL" -o /dev/null; then
        echo "Server up at $URL (PID $SERVER_PID, log: $LOG_FILE)"
        echo "Stop it with: scripts/stop-app.sh"
        exit 0
    fi
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        echo "ERROR: php artisan serve exited immediately — see $LOG_FILE" >&2
        rm -f "$PID_FILE"
        exit 1
    fi
    sleep 1
    ELAPSED=$((ELAPSED + 1))
done

echo "ERROR: server did not answer at $URL within 30s — killing PID $SERVER_PID; see $LOG_FILE" >&2
kill "$SERVER_PID" 2>/dev/null || true
rm -f "$PID_FILE"
exit 1
