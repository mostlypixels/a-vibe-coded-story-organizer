#!/usr/bin/env bash
#
# spec-locate.sh — locate a feature folder under .specs/ by name.
#
# Implements the folder-location rule shared by the whole spec pipeline: a feature
# sits either flat under draft/ (.specs/draft/<name>/) or in a month bucket
# (.specs/<status>/<YYYY-MM>/<name>/). Prints one line per match:
#
#     <status><TAB><absolute-path>
#
# ordered earliest-lifecycle-first (draft < expanded < planned < shipped), so a
# caller hitting a name collision can take the FIRST line as "the active feature"
# (the newest, least-advanced work — the collision is resolved by the auto-suffix
# rule when that folder next moves; see .specs/README.md → Name-collision handling).
# Exits 1 with a message when no folder matches.
#
# Called by: .claude/skills/mp-spec-expander, .claude/skills/plan-tasks,
# .claude/skills/ship-plan, .claude/agents/plan-implementer.md, and the sibling
# scripts spec-advance.sh and plan-next-task.sh.

set -euo pipefail

if [ $# -ne 1 ] || [ -z "$1" ]; then
    echo "usage: spec-locate.sh <feature-name>" >&2
    exit 1
fi

name="$1"
root="$(git rev-parse --show-toplevel)"
specs="$root/.specs"

found=0

# The four lifecycle stages, in order — draft is flat, the rest are month-bucketed.
if [ -d "$specs/draft/$name" ]; then
    printf 'draft\t%s\n' "$specs/draft/$name"
    found=1
fi

for status in expanded planned shipped; do
    for dir in "$specs/$status"/*/"$name"; do
        if [ -d "$dir" ]; then
            printf '%s\t%s\n' "$status" "$dir"
            found=1
        fi
    done
done

if [ "$found" -eq 0 ]; then
    echo "spec-locate.sh: no feature named '$name' under .specs/ (looked in .specs/draft/$name/ and .specs/*/*/$name/)" >&2
    exit 1
fi
