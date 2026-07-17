#!/usr/bin/env bash
#
# plan-next-task.sh — list a feature's remaining plan tasks, in execution order.
#
# Locates the feature folder via spec-locate.sh (earliest lifecycle stage on a
# name collision) and prints the NN-*.md task files directly under its plan/
# that have NOT yet been moved to plan/implemented/ — sorted numerically, one
# absolute path per line. 00-overview.md is the plan's manual, never a task, so
# it is always excluded. Callers wanting only "the next task" take the first line.
#
# Exit codes:
#   0 — remaining tasks printed
#   1 — feature not found, or it has no plan/ folder (message on stderr)
#   2 — plan/ exists but every task is already implemented (prints nothing)
#
# Called by: .claude/skills/ship-plan and .claude/agents/plan-implementer.md.

set -euo pipefail

if [ $# -ne 1 ] || [ -z "$1" ]; then
    echo "usage: plan-next-task.sh <feature-name>" >&2
    exit 1
fi

name="$1"
root="$(git rev-parse --show-toplevel)"

# Locate the feature; on a name collision take the first (earliest-stage) match.
located="$("$root/scripts/spec-locate.sh" "$name" | head -n 1)"
dir="${located#*$'\t'}"

plan="$dir/plan"
if [ ! -d "$plan" ]; then
    echo "plan-next-task.sh: $dir has no plan/ folder — run /plan-tasks $name first" >&2
    exit 1
fi

remaining=0
# The [0-9][0-9]-*.md glob expands in lexical = numeric order for NN prefixes.
for task in "$plan"/[0-9][0-9]-*.md; do
    [ -f "$task" ] || continue
    base="$(basename "$task")"
    [ "$base" = "00-overview.md" ] && continue
    [ -f "$plan/implemented/$base" ] && continue
    printf '%s\n' "$task"
    remaining=1
done

# Plan exists but nothing is left to do: silent success-with-a-twist, exit 2.
if [ "$remaining" -eq 0 ]; then
    exit 2
fi
