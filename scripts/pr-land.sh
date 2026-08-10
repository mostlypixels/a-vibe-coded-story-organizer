#!/usr/bin/env bash
#
# pr-land.sh — land the current feature branch on master via the protected-branch
# PR ritual: push the branch, open a PR, stamp the PR number onto the changelog
# heading, arm squash auto-merge, watch CI, merge, and only report success once
# the PR state is actually MERGED (auto-merge is silent, so "armed" is not
# "shipped" — this script enforces the difference).
#
# Auto-merge is a safety net here, not the mechanism: the script blocks on the
# checks either way, so once they are green it merges the PR itself rather than
# depending on an arming call that races CI registration (see arm_auto_merge).
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

# Retries for arming auto-merge. See arm_auto_merge() for why one attempt is not
# enough — the window where GitHub accepts the mutation opens late and shuts again.
ARM_ATTEMPTS=6
ARM_INTERVAL_SECONDS=5

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

# Stamp the PR number onto the newest dated CHANGELOG heading.
#
# The changelog convention wants `## YYYY-MM-DD — <title> (#PR)`, but the number
# cannot be known when the entry is written: it only exists once `gh pr create`
# has run, and by then the entry is already committed. Every entry therefore
# needed a manual follow-up. This closes the loop automatically.
#
# Only the FIRST dated heading in the file is considered — new entries always go
# at the top, so that is this PR's — and it is left alone if it already carries a
# number. Returns 1 (no edit made) when there is nothing to stamp, which callers
# treat as normal, not as a failure.
#
# Takes the file path so it can be unit-tested against a fixture.
backfill_changelog_heading() {
    local pr_number="$1" file="$2"

    [ -f "$file" ] || return 1

    local temp_file="$file.pr-land.tmp"

    # Interval expressions ({4}) are deliberately avoided: this has to behave the
    # same under mawk (the default awk on Ubuntu CI) as under gawk.
    if awk -v number="$pr_number" '
        !seen && /^## [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] / {
            seen = 1
            if ($0 !~ /\(#[0-9][0-9]*\)[ \t]*$/) {
                sub(/[ \t]+$/, "")
                $0 = $0 " (#" number ")"
                edited = 1
            }
        }
        { print }
        END { exit(edited ? 0 : 1) }
    ' "$file" > "$temp_file"; then
        mv "$temp_file" "$file"
        return 0
    fi

    rm -f "$temp_file"
    return 1
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

# Arm squash auto-merge, retrying — a single attempt races CI registration.
#
# GitHub refuses `enablePullRequestAutoMerge` at BOTH ends of the window that
# opens right after a push, and the two failures look nothing alike:
#
#   "Pull Request is not mergeable" — mergeability is still being computed
#       asynchronously (`mergeable: UNKNOWN`), i.e. we asked too early.
#   "Pull request is in clean status" — no required check has registered yet, so
#       the PR looks immediately mergeable and auto-merge has nothing to wait
#       for. Same race, seen from the other side.
#
# Neither means the repo setting is off, which is what a single attempt used to
# conclude — so both PRs of a session could fail to arm and then sit open until
# the poll cap. Retrying spans the window; only an exhausted retry budget is
# evidence of an actual repo setting.
#
# Best-effort by design: main() lands the PR directly once checks are green, so
# auto-merge is the safety net for this script dying, not the mechanism.
arm_auto_merge() {
    local pr_number="$1" attempt

    for attempt in $(seq 1 "$ARM_ATTEMPTS"); do
        if gh pr merge "$pr_number" --squash --auto --delete-branch 2>/dev/null; then
            return 0
        fi
        [ "$attempt" -lt "$ARM_ATTEMPTS" ] && sleep "$ARM_INTERVAL_SECONDS"
    done

    return 1
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

    # Stamp the number onto this PR's changelog heading and push the fixup. This
    # happens BEFORE auto-merge is armed on purpose: once armed, the PR can land
    # the moment checks go green, and a push racing that merge would be lost.
    # Guarded on the branch actually having touched CHANGELOG.md, so a PR without
    # an entry can never stamp its number onto some earlier, unnumbered heading.
    if git diff --name-only "origin/master...HEAD" | grep -qx "CHANGELOG.md"; then
        if backfill_changelog_heading "$pr_number" "CHANGELOG.md"; then
            echo "pr-land.sh: stamping (#$pr_number) onto the changelog heading..."
            # --only CHANGELOG.md is load-bearing. This repo routinely carries
            # the user's unrelated WIP, some of it already staged, and a commit
            # without a pathspec sweeps every staged path into the PR.
            #
            # No model name in the trailer: this commit is written by whichever
            # model runs the script, and a hardcoded one silently goes stale.
            git commit --only CHANGELOG.md --message "Backfill the PR number on the changelog heading

The number only exists once the PR is open, so pr-land.sh stamps it here.

Co-Authored-By: Claude <noreply@anthropic.com>"
            git push
        else
            echo "pr-land.sh: changelog heading already numbered (or no dated heading) — nothing to stamp."
        fi
    else
        echo "pr-land.sh: branch does not touch CHANGELOG.md — skipping the PR-number stamp."
    fi

    echo "pr-land.sh: arming squash auto-merge..."
    if arm_auto_merge "$pr_number"; then
        echo "pr-land.sh: auto-merge armed — CI does the waiting."
    else
        # Not fatal: this script blocks on the checks anyway and merges directly
        # below. Auto-merge only matters if the script dies mid-watch.
        echo "pr-land.sh: WARNING: could not arm auto-merge after $ARM_ATTEMPTS attempts (repo setting may be off)." >&2
        echo "pr-land.sh: continuing — the PR will be merged directly once checks are green." >&2
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
    echo "pr-land.sh: checks are green — landing PR #$pr_number..."

    # Auto-merge can lag a few seconds after checks go green, so give it one
    # interval before stepping in. From the second attempt on, merge directly:
    # we are already blocking here with green checks, so there is nothing left
    # to wait for, and an unarmed PR would otherwise sit until the cap expires
    # and be reported as a failure it isn't.
    local attempt state merge_attempted=0
    for attempt in $(seq 1 "$MERGE_POLL_ATTEMPTS"); do
        state="$(gh pr view "$pr_number" --json state --jq .state)"
        if [ "$state" = "MERGED" ]; then
            echo "pr-land.sh: PR #$pr_number is MERGED."
            echo "pr-land.sh: updating local master..."
            git checkout master
            git pull
            # The squash merge means git branch -d won't see the branch as merged;
            # the MERGED state above is the real safety check, so force-delete.
            #
            # Guarded on the branch still existing: `gh pr merge --delete-branch`
            # does its own local cleanup, so by the time we get here the branch
            # is often already gone (the checkout above then reports "Already on
            # 'master'"). Unguarded, that made `git branch -D` fail under set -e
            # and turned a completed land into exit 1 — a false failure at the
            # last line of a successful run.
            if git show-ref --quiet --verify "refs/heads/$branch"; then
                echo "pr-land.sh: deleting local branch '$branch' (remote twin already deleted by the merge)..."
                git branch -D "$branch"
            else
                echo "pr-land.sh: local branch '$branch' already removed by gh — nothing to clean up."
            fi
            echo "pr-land.sh: landed as merge commit $(git rev-parse HEAD) — $(git log -1 --format=%s)"
            exit 0
        fi
        if [ "$attempt" -ge 2 ] && [ "$merge_attempted" -eq 0 ]; then
            merge_attempted=1
            echo "pr-land.sh: auto-merge has not landed it (state $state) — merging directly..."
            # Tolerate failure: a merge that raced auto-merge is already done,
            # and any real blocker (a missing review, a conflict) shows up as a
            # non-MERGED state on the next pass and is reported at the cap.
            gh pr merge "$pr_number" --squash --delete-branch || true
            continue
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
