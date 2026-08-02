---
name: ship-plan
description: Drive a feature's .specs/planned/<name>/plan/ task-by-task through the plan-implementer agent until every task is implemented, move the folder to .specs/shipped/, then offer to branch and commit. Use when asked to "ship", "run", or "finish" a feature's plan, or to implement all remaining plan tasks for a named feature.
---

# ship-plan

Run an entire feature plan to completion, one task at a time, instead of launching the
implementer agent by hand for each.

**Argument:** the feature name. Locate its folder with `bash scripts/spec-locate.sh <name>`
(a planned feature sits at `.specs/planned/<YYYY-MM>/<name>/`); `<dir>` below means that
folder, and its `plan/` subfolder is what this skill runs.

## Steps

1. **Validate.** Confirm `<dir>/plan/00-overview.md` exists. No folder or no `plan/` → tell
   the user to run `/mp-plan-tasks <name>` first and stop. Multiple lines (name collision) →
   take the first; the suffix rule resolves it at step 8.

2. **List remaining tasks** with `bash scripts/plan-next-task.sh <name>`. Exit 2 → report the
   feature is fully implemented and stop.

3. **Drift check — not a re-grill.** The plan was already grilled at `mp-plan-tasks` time; do
   not repeat that interview. Compare `<dir>/plan/` against `<dir>/expanded/` and the
   **Feedback & decisions** in `resolution-log.md`, asking one question: has anything
   material shifted? (task added/dropped/reordered, a binding decision reversed, a fresh open
   question the implementer will hit head-on). Nothing → say so in one line and run. Something
   → invoke **`grilling`** on the drifted points only, fold the answers into the affected task
   files and `resolution-log.md`, then proceed. Never silently implement over a divergence.

4. **Run strictly sequentially.** Tasks are dependency-ordered by number; never launch two at
   once.

   > [!IMPORTANT]
   > **This loop runs straight through with no checkpoint between tasks.** If the user wants
   > one task at a time — to check quota, review each result, or resume a partly-finished plan
   > — **don't use this skill**: launch `plan-implementer` directly, once per task. That's the
   > supported mode, not a workaround.

   For the lowest-numbered remaining task:
   - Check `bash scripts/claude-usage.sh` first; if the remaining session budget won't cover
     the next task's weight, say so and stop at this boundary rather than starting it.
   - Launch `plan-implementer` (`Agent` tool, `subagent_type: "plan-implementer"`) with a
     prompt naming the feature and, if useful, the task number. **Nothing else** — the agent
     self-discovers prior progress, including `resolution-log.md`. It defaults to Sonnet; pass
     `model: "opus"` when the task file or `00-overview.md` flags a task as tricky (broad
     refactor, subtle invariants, gnarly JS).
   - Wait for completion, then confirm the task's `.md` moved to `plan/implemented/`.
   - Failed verification, or the file still in place → **stop the loop immediately** and
     surface the failure. Never start the next task on top of a broken one.
   - Otherwise repeat with the next task.

   Track progress from `plan/implemented/` + `resolution-log.md`; don't hand-maintain a
   progress table.

5. **Final sanity pass.** Full test suite + linter once more across everything the loop
   built. **With a UI/JS surface, green is not enough** — apply the same runtime verification
   `plan-implementer` uses: build the frontend, confirm the app serves the build (not a stale
   `public/hot` pointer), and drive the key flow via **`run-imagoldfish`** (or hand the user
   the exact click-path). Frontend regressions routinely pass PHPUnit.

6. **Consolidate the resolution log.** Fold into `<dir>/resolution-log.md` anything the loop
   produced that the agents couldn't log: feedback the *user* gave you, and issues found in
   the final pass. Same rule as the agent's — deviations, issues → resolutions, and decisions
   only. It is an exception log, not a run journal: a task that went to plan gets no entry.

7. **Report** a summary: each task, what it built, test counts, how any runtime surface was
   verified, and the deviations/issues logged (point at `resolution-log.md`).

8. **Stamp and move the spec** with `bash scripts/spec-advance.sh <name> shipped`. The script
   owns the mechanics (status + `shipped:` date, the collision-suffix rule, the `git mv` into
   `.specs/shipped/<YYYY-MM>/`) and prints the final path. Run it *before* asking about the
   commit, so stamp and move ride in the implementation commit — the spec's git history then
   points at the commit that shipped it. Only add `commit: <short-hash>` when stamping
   retroactively, after the implementation commit exists.

9. **Ask before committing; then use `ship-pr`.** Never commit automatically. Once confirmed,
   invoke **`ship-pr`**, which owns branch → commit (with CHANGELOG entry) → push → PR →
   auto-merge. Don't reimplement it here.

   One staging detail specific to this flow: step 8 already moved the folder, so the old
   `.specs/planned/…` path is gone and staging it fails with `fatal: pathspec … did not
   match any files`. Use `git add -A .specs/` to capture the rename plus the new
   `plan/implemented/`, `expanded/` and `resolution-log.md` files in one go, then stage the
   source/test/doc changes.

## Note

This skill is orchestration only. All implementation happens inside `plan-implementer` runs,
which are independent per task and self-discover state — don't duplicate that discovery here.
