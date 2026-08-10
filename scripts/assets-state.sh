#!/usr/bin/env bash
# assets-state.sh — check that the app would actually serve what you just built.
#
# Three states make a page look broken while the test suite stays green, because
# tests never load an asset or touch the dev database:
#
#   1. public/hot exists     — @vite points every page at a Vite dev server that
#                              is usually not running, so no CSS or JS loads.
#   2. public/build missing  — nothing was ever built (or the build failed).
#   3. pending migrations    — the dev SQLite database is behind the code; pages
#                              500 with "no such table" (tests use a fresh
#                              in-memory database and cannot catch this).
#
# It only reports; running `npm run build` or `php artisan migrate` is the
# caller's decision. Prints one OK/FAIL line per check.
#
# Usage: scripts/assets-state.sh
#
# Exit codes:
#   0 — all three checks pass, the build is servable
#   1 — at least one check failed (the fix is printed with it)
#   2 — bad arguments
#
# Callers: scripts/serve-app.sh, .claude/agents/plan-implementer.md
# (its "runtime surface" verification step).
set -euo pipefail

if [ $# -ne 0 ]; then
    echo "usage: scripts/assets-state.sh" >&2
    exit 2
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

failed=0

fail() {
    echo "FAIL  $1" >&2
    echo "      fix: $2" >&2
    failed=1
}

if [ -e public/hot ]; then
    fail "public/hot exists — @vite serves from a Vite dev server, not the build" \
         "rm public/hot (or run 'npm run build', which clears it)"
else
    echo "OK    public/hot absent — @vite serves the build"
fi

if [ ! -d public/build ] || [ ! -f public/build/manifest.json ]; then
    fail "public/build is missing or has no manifest.json" \
         "npm run build"
else
    echo "OK    public/build/manifest.json present"
fi

# migrate:status also fails when the database file itself is missing or
# unreadable, which is the same class of problem, so we do not separate them.
if php artisan migrate:status --pending 2>/dev/null | grep -q 'No pending migrations'; then
    echo "OK    dev database is up to date"
else
    fail "the dev database has pending migrations (or migrate:status failed)" \
         "php artisan migrate"
fi

exit "$failed"
