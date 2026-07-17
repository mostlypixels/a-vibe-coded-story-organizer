#!/usr/bin/env bash
#
# spec-advance.sh — advance a feature one lifecycle stage: draft → expanded →
# planned → shipped.
#
# Given a feature name and the stage it should enter, this script does the whole
# stamp-and-move ritual from .specs/README.md in one atomic step, so the folder
# location and the spec.md frontmatter can never disagree:
#
#   1. Locates the folder via spec-locate.sh (earliest lifecycle stage on a name
#      collision — that's the active, un-advanced work).
#   2. Validates the transition is exactly one stage forward.
#   3. Stamps spec.md's YAML frontmatter: `status: <new-status>` plus
#      `<new-status>: YYYY-MM-DD` (today). Adds a frontmatter block if the file
#      has none. Touches nothing else in the file.
#   4. Applies the name-collision auto-suffix rule (README → Name-collision
#      handling): if another feature already holds the destination name, the
#      folder moves as <name>-YYYY-MM-DD (or <name>-YYYY-MM-DD-HHMM on a
#      same-day double collision).
#   5. Moves the folder to .specs/<new-status>/<YYYY-MM>/<final-name>/ (month =
#      today, matching the stamp) with `git mv`, falling back to plain `mv` when
#      the folder is still untracked (a fresh draft).
#
# Prints the final absolute path of the moved folder — its basename is the
# (possibly suffixed) name to pass to the next pipeline stage.
#
# Called by: .claude/skills/mp-spec-expander (→ expanded),
# .claude/skills/plan-tasks (→ planned), .claude/skills/ship-plan (→ shipped).

set -euo pipefail

if [ $# -ne 2 ] || [ -z "$1" ] || [ -z "$2" ]; then
    echo "usage: spec-advance.sh <feature-name> <new-status>   (new-status: expanded | planned | shipped)" >&2
    exit 1
fi

name="$1"
new_status="$2"
root="$(git rev-parse --show-toplevel)"
specs="$root/.specs"

# --- Validate the transition: one stage forward only. ---------------------------
case "$new_status" in
    expanded) required_from="draft" ;;
    planned)  required_from="expanded" ;;
    shipped)  required_from="planned" ;;
    *)
        echo "spec-advance.sh: invalid target status '$new_status' (lifecycle: draft -> expanded -> planned -> shipped)" >&2
        exit 1
        ;;
esac

# Locate the feature; on a name collision take the first (earliest-stage) match.
located="$("$root/scripts/spec-locate.sh" "$name" | head -n 1)"
current_status="${located%%$'\t'*}"
dir="${located#*$'\t'}"

if [ "$current_status" != "$required_from" ]; then
    echo "spec-advance.sh: '$name' is at stage '$current_status', but only '$required_from' can advance to '$new_status' (draft -> expanded -> planned -> shipped)" >&2
    exit 1
fi

spec="$dir/spec.md"
if [ ! -f "$spec" ]; then
    echo "spec-advance.sh: $dir has no spec.md to stamp" >&2
    exit 1
fi

today="$(date +%Y-%m-%d)"
month="$(date +%Y-%m)"

# --- Stamp the frontmatter (the only edit ever made to the source spec). --------
tmp="$spec.tmp"
if head -n 1 "$spec" | grep -q '^---[[:space:]]*$'; then
    # Existing frontmatter: rewrite `status:` in place (stamping the stage date
    # right after it), drop any stale `<new-status>:` line, keep everything else.
    awk -v ns="$new_status" -v today="$today" '
        NR == 1 && /^---[[:space:]]*$/ { in_fm = 1; print; next }
        in_fm && /^---[[:space:]]*$/ {
            # Closing fence: if the block had no status: line, add the stamps here.
            if (!stamped) { print "status: " ns; print ns ": " today }
            in_fm = 0; print; next
        }
        in_fm && /^status:/ { print "status: " ns; print ns ": " today; stamped = 1; next }
        in_fm && index($0, ns ":") == 1 { next }
        { print }
    ' "$spec" > "$tmp"
else
    # No frontmatter (an implicit draft): prepend a fresh block.
    { printf -- '---\nstatus: %s\n%s: %s\n---\n' "$new_status" "$new_status" "$today"; cat "$spec"; } > "$tmp"
fi
mv "$tmp" "$spec"

# --- Name-collision auto-suffix before the move. --------------------------------
# True when any feature folder OTHER than the one being moved already claims $1.
name_taken() {
    local candidate="$1" match
    for match in "$specs/draft/$candidate" "$specs"/*/*/"$candidate"; do
        if [ -d "$match" ] && [ "$match" != "$dir" ]; then
            return 0
        fi
    done
    return 1
}

final_name="$name"
if name_taken "$final_name"; then
    final_name="$name-$today"
    if name_taken "$final_name"; then
        final_name="$name-$today-$(date +%H%M)"
    fi
fi

# --- Move so the folder location matches the fresh stamp. -----------------------
dest_bucket="$specs/$new_status/$month"
dest="$dest_bucket/$final_name"
mkdir -p "$dest_bucket"

# git mv preserves history for tracked folders; a brand-new draft may still be
# untracked, in which case git mv refuses and a plain mv is correct.
if ! git -C "$root" mv "$dir" "$dest" 2>/dev/null; then
    mv "$dir" "$dest"
fi

echo "$dest"
