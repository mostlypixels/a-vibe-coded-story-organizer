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

5. **Push and open the PR.**
   ```bash
   git push -u origin <branch>
   gh pr create --title "..." --body "..."
   ```
   The PR description carries the richer rationale (per the changelog convention):
   what changed, why, and how it was verified (test counts, lint, any runtime check).

6. **Arm auto-merge immediately** — the repo has auto-merge enabled, so CI does the
   waiting, not you:
   ```bash
   gh pr merge <number> --squash --auto --delete-branch
   ```
   If this errors with "Auto merge is not allowed", the repo setting was turned off;
   fall back to watching checks (`gh pr checks <number> --watch`, in the background)
   and squash-merging manually when green.

7. **Confirm it landed before reporting done.** Auto-merge is silent — do not declare
   the change shipped on the strength of step 6 alone. Watch `gh pr checks <number>
   --watch` in the background; when it finishes, verify the PR state is `MERGED`
   (`gh pr view <number> --json state,mergedAt`). If a check failed, the PR is still
   open: surface the failing check's output and fix forward on the same branch.
   After the merge: `git checkout master && git pull` so the local clone matches, and
   report the merge commit.

## Notes

- Only invoke this when the user asked for a commit/ship — committing stays an explicit
  user choice (per `CLAUDE.md` and the ship-plan flow, which asks first and then
  delegates here).
- A requested commit implies the full ritual: since `master` rejects direct pushes,
  "commit this" can only mean branch + PR + merge — don't stop at a local commit unless
  the user says so.
- One PR per coherent change set. Don't batch unrelated work into the ritual because
  the branch already exists.
