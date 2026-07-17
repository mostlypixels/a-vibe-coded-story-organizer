#!/usr/bin/env bash
#
# pr-land.sh — land the current feature branch on master via the protected-branch
# PR ritual: push the branch, open a PR, arm squash auto-merge, watch CI, and
# only report success once the PR state is actually MERGED (auto-merge is silent,
# so "armed" is not "shipped" — this script enforces the difference).
#
#     usage: pr-land.sh <title> <body-file>
#
# Assumes the change set is already committed on a feature branch (the judgment
# steps — what to stage, changelog entry, branch name, commit message — stay in
# the calling skill). Run it in the background and tail its output: it blocks on
# `gh pr checks --watch` and then polls until the merge lands (~2 min cap).
#
# Exit codes: 0 = merged and local master updated; non-zero = refused to run,
# a CI check failed, or the merge did not land within the cap (the PR URL and
# state are printed so the caller can fix forward on the same branch).
#
# Auth: `gh` handles its own authentication; no secrets are read or passed here.
#
# Called by: .claude/skills/ship-pr (steps 5–7 of the ritual).

set -euo pipefail

# How long to keep polling for the merge after checks finish. Auto-merge can lag
# a few seconds behind the last check going green.
MERGE_POLL_ATTEMPTS=24
MERGE_POLL_INTERVAL_SECONDS=5   # 24 * 5s = 2 min cap

usage() {
    echo "usage: pr-land.sh <title> <body-file>" >&2
}

# Validate arguments and preconditions. Split out from main() so the refusal
# paths can be exercised without touching the network.
validate() {
    local title="${1-}" body_file="${2-}"

    if [ $# -ne 2 ] || [ -z "$title" ] || [ -z "$body_file" ]; then
        usage
        return 1
    fi

    if [ ! -f "$body_file" ]; then
        echo "pr-land.sh: body file not found: $body_file" >&2
        return 1
    fi

    return 0
}

# Refuse to run on master — the whole point of the ritual is that master only
# moves via PRs. Takes the branch name as an argument so it can be tested.
refuse_master() {
    local branch="$1"

    if [ "$branch" = "master" ]; then
        echo "pr-land.sh: refusing to run on master — create a feature branch first (git checkout -b <name>)" >&2
        return 1
    fi

    return 0
}

# Warn (but do not abort) when the tree is dirty: this repo routinely carries
# the user's unrelated WIP (e.g. welcome.blade.php), and only already-committed
# work ships anyway — the push sends commits, not the working tree.
warn_dirty_tree() {
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "pr-land.sh: WARNING: working tree has uncommitted changes to tracked files — they will NOT be part of the PR:" >&2
        git status --short --untracked-files=no >&2
        echo "pr-land.sh: proceeding anyway (only committed work on this branch ships)." >&2
    fi
}

main() {
    validate "$@" || exit 1
    local title="$1" body_file="$2"

    cd "$(git rev-parse --show-toplevel)"

    local branch
    branch="$(git branch --show-current)"
    refuse_master "$branch" || exit 1

    warn_dirty_tree

    echo "pr-land.sh: pushing branch '$branch' to origin..."
    git push -u origin "$branch"

    echo "pr-land.sh: opening PR..."
    local pr_url pr_number
    pr_url="$(gh pr create --title "$title" --body-file "$body_file")"
    # `gh pr create` prints the PR URL; the number is its last path segment.
    pr_number="${pr_url##*/}"
    echo "pr-land.sh: opened PR #$pr_number: $pr_url"

    echo "pr-land.sh: arming squash auto-merge..."
    if gh pr merge "$pr_number" --squash --auto --delete-branch; then
        echo "pr-land.sh: auto-merge armed — CI does the waiting."
    else
        # The documented fallback (ship-pr skill): if the repo setting was turned
        # off, watch checks and squash-merge manually when green. We still watch
        # below either way; the manual merge is the caller's move if needed.
        echo "pr-land.sh: WARNING: could not arm auto-merge (repo setting may be off)." >&2
        echo "pr-land.sh: fallback: watch checks, then squash-merge manually when green:" >&2
        echo "pr-land.sh:     gh pr merge $pr_number --squash --delete-branch" >&2
    fi

    # Checks take a few seconds to register after the PR opens; "no checks
    # reported" from `gh pr checks` at this point means "not yet", not "failed".
    # Wait (bounded) for at least one check to appear before watching.
    echo "pr-land.sh: waiting for CI checks to register on PR #$pr_number..."
    local wait_attempt
    for wait_attempt in $(seq 1 18); do
        if gh pr checks "$pr_number" >/dev/null 2>&1 || [ "$(gh pr view "$pr_number" --json statusCheckRollup --jq '.statusCheckRollup | length')" -gt 0 ]; then
            break
        fi
        if [ "$wait_attempt" -eq 18 ]; then
            echo "pr-land.sh: FAILED: no CI checks appeared on PR #$pr_number after ~90s." >&2
            echo "pr-land.sh: PR: $pr_url" >&2
            exit 1
        fi
        sleep 5
    done

    echo "pr-land.sh: watching CI checks for PR #$pr_number (this blocks until they finish)..."
    local checks_status=0
    gh pr checks "$pr_number" --watch || checks_status=$?
    if [ "$checks_status" -ne 0 ]; then
        echo "pr-land.sh: FAILED: a CI check did not pass on PR #$pr_number — fix forward on branch '$branch'." >&2
        echo "pr-land.sh: PR: $pr_url" >&2
        exit "$checks_status"
    fi
    echo "pr-land.sh: checks are green — waiting for auto-merge to land..."

    # Auto-merge can lag a few seconds after checks go green; poll with a cap.
    local attempt state
    for attempt in $(seq 1 "$MERGE_POLL_ATTEMPTS"); do
        state="$(gh pr view "$pr_number" --json state --jq .state)"
        if [ "$state" = "MERGED" ]; then
            echo "pr-land.sh: PR #$pr_number is MERGED."
            echo "pr-land.sh: updating local master..."
            git checkout master
            git pull
            echo "pr-land.sh: landed as merge commit $(git rev-parse HEAD) — $(git log -1 --format=%s)"
            exit 0
        fi
        echo "pr-land.sh: PR state is $state (attempt $attempt/$MERGE_POLL_ATTEMPTS), retrying in ${MERGE_POLL_INTERVAL_SECONDS}s..."
        sleep "$MERGE_POLL_INTERVAL_SECONDS"
    done

    echo "pr-land.sh: FAILED: PR #$pr_number did not merge within the poll cap — last state: $state" >&2
    echo "pr-land.sh: PR: $pr_url" >&2
    exit 1
}

# Only run main when executed directly, so the functions above can be sourced
# and unit-tested (bash -c 'source ...; refuse_master master') without side effects.
if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
