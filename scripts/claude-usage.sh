#!/usr/bin/env bash
# Print Claude Code session/week limit usage.
#   claude-usage.sh          -> {"session_pct":29,"session_reset":"...","week_pct":51,"week_reset":"..."}
#   claude-usage.sh --text   -> "session 29% resets Aug 2, 7:09pm | week 51% resets Aug 5, 5:59pm"
#   claude-usage.sh --raw    -> full /usage panel
#
# JSON is the default because the callers are scripts and agents, not eyes.
# `--json` names that default explicitly. Any other argument is an error — a
# mistyped flag used to fall through to JSON and look like it had worked.
#
# Failures print one word and exit 1, so a caller can branch on the output without
# parsing an error message: "unavailable" (no claude, or the call failed) or
# "unparseable" (it answered, but not in the shape below).
set -euo pipefail

fail() { printf '%s\n' "$1"; exit 1; }

mode="${1:---json}"
case "$mode" in
  --json|--text|--raw) ;;
  *) echo "usage: claude-usage.sh [--json | --text | --raw]" >&2; exit 2 ;;
esac

command -v claude >/dev/null 2>&1 || fail unavailable

# MSYS_NO_PATHCONV stops Git Bash rewriting "/usage" into a Windows path.
raw=$(MSYS_NO_PATHCONV=1 claude -p "/usage" 2>&1) || fail unavailable

if [[ "$mode" == "--raw" ]]; then
  printf '%s\n' "$raw"
  exit 0
fi

parse() { # $1 = line prefix
  printf '%s\n' "$raw" | sed -n "s/^$1: \([0-9]*\)% used · resets \(.*\)$/\1|\2/p"
}

session=$(parse "Current session")
week=$(parse "Current week (all models)")
[[ -n "$session" ]] || fail unparseable

s_pct=${session%%|*}; s_reset=${session#*|}
w_pct=${week%%|*};    w_reset=${week#*|}

if [[ "$mode" == "--text" ]]; then
  printf 'session %s%% resets %s | week %s%% resets %s\n' "$s_pct" "$s_reset" "$w_pct" "$w_reset"
else
  printf '{"session_pct":%s,"session_reset":"%s","week_pct":%s,"week_reset":"%s"}\n' \
    "$s_pct" "$s_reset" "${w_pct:-null}" "$w_reset"
fi
