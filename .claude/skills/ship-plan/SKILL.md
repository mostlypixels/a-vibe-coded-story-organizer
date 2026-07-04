---
name: ship-plan
description: Drive a feature's .specs/<name>/plan/ task-by-task through the plan-implementer agent until every task is implemented, then offer to branch and commit. Use when asked to "ship", "run", or "finish" a feature's plan, or to implement all remaining plan tasks for a named feature.
---

# ship-plan

Automate running an entire feature plan (`.specs/<name>/plan/`) to completion, one
task at a time, instead of launching the implementer agent by hand for each task.

## Argument

A single argument: the feature name — the folder under `.specs/` whose `plan/`
subfolder holds the task files. Example: `/ship-plan codex` → `.specs/codex/plan/`.

## Steps

1. **Validate.** Confirm `.specs/<name>/plan/00-overview.md` exists. If the `plan/`
   folder doesn't exist at all, tell the user to run `/plan-tasks <name>` first (it
   generates this folder from an expanded spec) and stop.

2. **List remaining tasks.** `NN-*.md` files directly under `plan/` (excluding
   `00-overview.md`), sorted numerically, that are not already present under
   `plan/implemented/`. If none remain, report the feature is fully implemented and
   stop here.

3. **Run strictly sequentially.** Tasks are dependency-ordered by number, so never
   launch more than one at a time. For the lowest-numbered remaining task:
   - Launch the `plan-implementer` agent (`Agent` tool, `subagent_type:
     "plan-implementer"`) with a prompt naming the feature and, if useful, the specific
     task number. The agent discovers prior progress itself — you do not need to recap
     what earlier tasks built.
   - Wait for its completion notification.
   - Confirm the task's `.md` file moved to `plan/implemented/`.
   - If the agent reports failed verification or leaves the task file in place, **stop
     the loop immediately** and surface the failure to the user — do not start the next
     task on top of a broken one.
   - Otherwise, move to the next remaining task and repeat.

4. **Final sanity pass.** Once the loop empties, run this project's full test suite and
   linter once more (commands from `CLAUDE.md`'s Commands section) as a final check
   across everything the loop built.

5. **Report** a summary: each task, what it built, its test counts, and any deviations
   the agent flagged along the way.

6. **Ask before branching/committing.** Do not create a branch or commit automatically.
   Ask the user whether they want the accumulated changes committed (and to a new
   branch, or the current one) — this mirrors the manual confirm step used when the
   Codex feature shipped, and keeps a visible, hard-to-reverse git action as an explicit
   choice rather than an assumption.

## Notes

- This skill is the orchestration layer; all actual implementation happens inside
  `plan-implementer` agent runs, which are fully independent per task and self-discover
  state from the spec docs, `implemented/` folder, and working tree — don't duplicate
  that discovery work here.
- If the user only wants a single task run (not the whole remaining plan), just launch
  `plan-implementer` directly instead of this skill.
