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
   dated `## YYYY-MM-DD — <title>` section at the top, below `[Unreleased]`, dated
   today (adjust if the merge slips to another day). **Leave the `(#PR)` suffix off** —
   the number does not exist yet, and `pr-land.sh` stamps it onto the newest dated
   heading automatically once `gh pr create` has assigned one.

   Follow **Changelog → Entry style** in `CLAUDE.md` and *Writing style* below. Sections
   dated before `2026-08-02` predate the rule — don't copy their shape.

3. **Branch.** If still on `master`, create a short kebab-case feature branch named for
   the change set (`git checkout -b <name>`). If already on a feature branch, stay on it.

4. **Commit.** Stage the explicit paths, then commit with a message whose *body explains
   why* — the intent, not a diff restatement (that's this repo's per-commit record).
   End the body with the `Co-Authored-By: Claude` trailer per the harness rules.

5. **Land it.** Write the PR body to a temp file (what changed, why, how it was verified),
   then run the landing script **in the background** and relay its outcome:
   ```bash
   bash scripts/pr-land.sh "<title>" <body-file>
   ```
   - It does push → `gh pr create` → stamp `(#PR)` onto the newest dated changelog heading
     and push that fixup → arm squash auto-merge → watch checks → poll until `MERGED` →
     `git checkout master && git pull`, echoing progress as it goes.
   - The stamp is skipped when the branch doesn't touch `CHANGELOG.md` or the heading is
     already numbered — it needs nothing from you beyond leaving the suffix off in step 2.
   - **Armed is not shipped.** Auto-merge is silent; the script exits 0 only once the PR is
     `MERGED` and local master is updated. Don't call it shipped before that.
   - Auto-merge unavailable (repo setting off) → it prints the manual squash-merge fallback
     and keeps watching.
   - Non-zero exit — a failed CI check, or the merge missed its ~2 min poll cap — it prints
     the PR URL and state. Surface the failing check's output and fix forward on the branch.

   > [!WARNING]
   > **Do not touch the working tree while it runs.** Its last step is `git checkout master
   > && git pull`, which aborts on uncommitted edits to a tracked file it needs to move —
   > `CHANGELOG.md` above all, since the next change set's entry goes there. The PR merges
   > and the script still exits 1, which reads like a CI failure and is not one. When
   > shipping several change sets in a row, prepare the next one only after the landing
   > reports `MERGED`; a stash is the cheap way to hold work that is already written.

## Writing style

Three artifacts, three altitudes — say each thing once, in the one place that owns it.
Same rules as `CLAUDE.md` → Documentation → Verbosity. No length budgets.

| Artifact | Owns | Never |
|---|---|---|
| Changelog entry | what shipped, user-visible | class names, file paths, how, why |
| Commit body | why *this* commit exists, the intent | restating the diff, a file-by-file tour |
| PR body | the change set's rationale + verification | re-listing the changelog entries |

- **Bullets.** Prose only for a *why* that a bullet can't carry.
- **Verification is a line, not a report:** "`composer test` green (412), `composer lint` clean."
  No pasted output, no per-test narration.
- **No scaffolding** — no "## Summary" heading over three bullets, no closing recap, no
  "this PR aims to…".
- **Nothing speculative.** No future work, no alternatives considered, no caveats about
  things the diff doesn't touch.

## Notes

- Only invoke this when the user asked for a commit/ship — committing stays an explicit
  user choice (per `CLAUDE.md` and the ship-plan flow, which asks first and then
  delegates here).
- A requested commit implies the full ritual: since `master` rejects direct pushes,
  "commit this" can only mean branch + PR + merge — don't stop at a local commit unless
  the user says so.
- One PR per coherent change set. Don't batch unrelated work into the ritual because
  the branch already exists.
