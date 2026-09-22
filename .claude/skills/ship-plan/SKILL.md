---
name: ship-plan
description: Drive a feature's .specs/planned/<name>/plan/ task-by-task through the plan-implementer agent until every task is implemented, move the folder to .specs/shipped/, then offer to branch and commit. Use when asked to "ship", "run", or "finish" a feature's plan, or to implement all remaining plan tasks for a named feature.
---

# ship-plan

Run a whole feature plan through `plan-implementer`, one agent run per task, then ship it.
This skill only orchestrates: the agent does all implementation and reads its own progress.

To go one task at a time — to review each result, or to resume part-way — launch
`plan-implementer` directly, once per task. That is a supported mode.

**Argument:** the feature name. `bash scripts/spec-locate.sh <name>` finds its folder (a
planned feature sits at `.specs/planned/<YYYY-MM>/<name>/`). On several matches, take the first
line; step 8 resolves the name collision. Below, `<dir>` is that folder.

## Steps

1. **Validate.** `<dir>/plan/00-overview.md` must exist. Otherwise tell the user to run
   `/mp-plan-tasks <name>` first, and stop.

2. **List the remaining tasks** with `bash scripts/plan-next-task.sh <name>`. Exit 2 → report
   that the feature is fully implemented, and stop.

3. **Drift check.** The plan was grilled at planning time. This step asks one question: did
   something material shift since? Compare `<dir>/plan/` with `<dir>/expanded/` and the
   **Feedback & decisions** in `resolution-log.md` — a task added, dropped or reordered, a
   binding decision reversed, a new open question the implementer will hit.
   - No drift → say so in one line and continue.
   - Drift → run the **`grilling`** skill on the drifted points only, write the answers into
     the affected task files and `resolution-log.md`, then continue.

4. **Loop, one task at a time, lowest number first:**
   1. Run `bash scripts/claude-usage.sh`. If the remaining session budget will not cover this
      task, stop here and report.
   2. Launch `plan-implementer` (`Agent`, `subagent_type: "plan-implementer"`). The prompt
      names the feature and, when useful, the task number — nothing more. The agent runs on
      Sonnet; pass `model: "opus"` when the task file or `00-overview.md` flags the task as
      hard (broad refactor, subtle invariants, tricky JS).
   3. When it returns, confirm the task's `.md` is in `plan/implemented/`. If verification
      failed or the file is still in `plan/`, stop the loop and show the failure.
   4. Repeat until `plan-next-task.sh` exits 2.

   `plan/implemented/` and `resolution-log.md` are the progress record.

5. **Final check.** Run `bash scripts/verify.sh` over everything the loop built. For a UI or JS
   surface, also do the runtime checks from `plan-implementer` → *Verify*: `npm run build`,
   `bash scripts/assets-state.sh`, and the key flow in a browser through **`run-imagoldfish`**
   (or the exact click-path for the user). Frontend regressions often pass PHPUnit.

6. **Complete the resolution log.** Add what the agents could not log: feedback the user gave
   you, and issues from step 5. Exceptions only, same rule as the agent's.

7. **Report:** each task, what it built, test counts, how the runtime surface was verified,
   and a pointer to `resolution-log.md` for deviations and issues.

8. **Advance the spec** with `bash scripts/spec-advance.sh <name> shipped`. It stamps the
   status and `shipped:` date, applies the collision-suffix rule, `git mv`s the folder to
   `.specs/shipped/<YYYY-MM>/`, and prints the new path. Run it before the commit, so the move
   rides in the implementation commit. Add `commit: <short-hash>` only when stamping
   retroactively.

9. **Commit on request, through `ship-pr`.** Ask the user first. On a yes, invoke **`ship-pr`**
   for branch, commit, changelog, PR and merge.

   Staging for this flow: stage the move with `git add -A <old path> <new path>` — the
   `.specs/planned/…` path from step 1 and the path step 8 printed. That stages the rename
   and the new `plan/implemented/` and `resolution-log.md` files, and leaves other specs'
   work alone. Then stage source, test and doc changes by explicit path.
