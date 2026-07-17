#!/usr/bin/env bash
# serve-app.sh — start the imagoldfish dev server (php artisan serve) in the
# background with the pre-flight checks that the run-imagoldfish skill used to
# spell out inline: refuse on a stale public/hot file (@vite would point at a
# dead Vite dev server), refuse if public/build is missing, refuse if the dev
# SQLite database has pending migrations (green tests do NOT prove the dev DB
# is current). Records the server PID in scripts/.serve-app.pid and polls the
# URL until it answers. Idempotent: exits 0 if the recorded PID is already a
# live server. Stop the server with scripts/stop-app.sh.
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

# Gotcha 1: a leftover public/hot file makes @vite target a (dead) Vite dev
# server instead of the built assets — every page would load without CSS/JS.
if [ -e public/hot ]; then
    echo "ERROR: public/hot exists — @vite will try to reach a Vite dev server instead of serving the build, and every page will fail to load its assets." >&2
    echo "Fix: remove it (rm public/hot), or run 'npm run build' (which clears it)." >&2
    exit 1
fi

# Gotcha 2: no built assets at all.
if [ ! -d public/build ] || [ ! -f public/build/manifest.json ]; then
    echo "ERROR: public/build is missing (or has no manifest.json) — run 'npm run build' first." >&2
    exit 1
fi

# Gotcha 3: the dev SQLite DB can be behind migrations even when the test
# suite is green (tests use a fresh in-memory DB). A stale dev DB 500s with
# "no such table". We only check — running migrations is the caller's call.
if ! php artisan migrate:status --pending 2>/dev/null | grep -q 'No pending migrations'; then
    echo "ERROR: the dev database has pending migrations (or migrate:status failed) — run 'php artisan migrate'." >&2
    exit 1
fi

mkdir -p storage/logs
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
