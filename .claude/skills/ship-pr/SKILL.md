---
name: ship-pr
description: Ship the working tree's changes to master via the protected-branch ritual — branch, commit, push, open a PR, arm squash auto-merge, and confirm it landed. Use when asked to commit/ship/PR a change set, and from other skills (ship-plan) that end in a commit. Not for reviewing a PR (that's /review) or for implementing anything — the changes must already exist.
---

# ship-pr

`master` is protected: direct pushes are rejected, so every change set ships as
branch → PR → green `tests` CI check → squash-merge (0 approvals; self-merge is fine).
This skill is that ritual, end to end. It assumes the work is already done and verified —
run the project's test suite and lint (per `CLAUDE.md`'s **Commands** section) *before*
invoking this, not as part of it.

## Steps

1. **Check what's in the tree.** `git status --short`. Separate *your* change set from
   unrelated dirty files (e.g. the user's own manual WIP): you will stage **explicit
   paths only** — never `git add -A` / `git add .` at the repo root. If a dirty file's
   ownership is unclear, ask rather than commit someone else's half-done work.

2. **Update `CHANGELOG.md` as part of the change set** (skip only if the diff is
   docs/tooling so trivial it needs no entry). Per the convention in `CLAUDE.md`: add a
   dated `## YYYY-MM-DD — <title> (#PR)` section at the top, below `[Unreleased]`,
   dated today (adjust if the merge slips to another day; the PR number can be
   backfilled by editing the heading after `gh pr create` prints it, or left off).

3. **Branch.** If still on `master`, create a short kebab-case feature branch named for
   the change set (`git checkout -b <name>`). If already on a feature branch, stay on it.

4. **Commit.** Stage the explicit paths, then commit with a message whose *body explains
   why* — the intent, not a diff restatement (that's this repo's per-commit record).
   End the body with the `Co-Authored-By: Claude` trailer per the harness rules.

5. **Land it.** Write the PR body to a temp file — it carries the richer rationale
   (per the changelog convention): what changed, why, and how it was verified (test
   counts, lint, any runtime check). Then run the landing script **in the background**
   and relay its outcome:
   ```bash
   bash scripts/pr-land.sh "<title>" <body-file>
   ```
   The script does push → `gh pr create` → arm squash auto-merge → watch checks →
   poll until the PR state is `MERGED` → `git checkout master && git pull`, echoing
   progress lines as it goes. The *why* still matters: auto-merge is silent, so
   "armed" is not "shipped" — the script enforces this by exiting 0 only once the
   PR is actually `MERGED` and local master is updated. Do not declare the change
   shipped until it does. If auto-merge can't be armed (repo setting off), it prints
   the manual squash-merge fallback and keeps watching. If it exits non-zero — a CI
   check failed, or the merge didn't land within its ~2 min poll cap — it prints the
   PR URL and state: surface the failing check's output and fix forward on the same
   branch.

## Notes

- Only invoke this when the user asked for a commit/ship — committing stays an explicit
  user choice (per `CLAUDE.md` and the ship-plan flow, which asks first and then
  delegates here).
- A requested commit implies the full ritual: since `master` rejects direct pushes,
  "commit this" can only mean branch + PR + merge — don't stop at a local commit unless
  the user says so.
- One PR per coherent change set. Don't batch unrelated work into the ritual because
  the branch already exists.
