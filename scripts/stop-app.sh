#!/usr/bin/env bash
# stop-app.sh — stop the dev server started by scripts/serve-app.sh by killing
# the exact PID recorded in scripts/.serve-app.pid, then remove the PID file.
# Killing a recorded PID needs no process-name matching, so this replaces the
# fragile "hunt for php.exe whose CommandLine matches 'artisan serve'" ritual.
# Idempotent: exits 0 (with a message) if there is no PID file or the process
# is already gone.
#
# Usage: scripts/stop-app.sh
#
# Callers: .claude/skills/run-imagoldfish/SKILL.md
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

PID_FILE="scripts/.serve-app.pid"

if [ ! -f "$PID_FILE" ]; then
    echo "No PID file ($PID_FILE) — no server to stop."
    exit 0
fi

SERVER_PID="$(cat "$PID_FILE")"

if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID"
    echo "Stopped dev server (PID $SERVER_PID)."
else
    echo "Server (PID ${SERVER_PID:-unknown}) already gone — cleaning up PID file."
fi

rm -f "$PID_FILE"
exit 0
