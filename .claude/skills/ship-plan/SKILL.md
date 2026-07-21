---
name: ship-plan
description: Drive a feature's .specs/planned/<name>/plan/ task-by-task through the plan-implementer agent until every task is implemented, move the folder to .specs/shipped/, then offer to branch and commit. Use when asked to "ship", "run", or "finish" a feature's plan, or to implement all remaining plan tasks for a named feature.
---

# ship-plan

Automate running an entire feature plan (`.specs/planned/<name>/plan/`) to completion, one
task at a time, instead of launching the implementer agent by hand for each task.

## Argument

A single argument: the feature name. Locate the feature folder with
`bash scripts/spec-locate.sh <name>` — a planned feature sits at
`.specs/planned/<YYYY-MM>/<name>/` (stages past draft bucket features by month) — and
use its `plan/` subfolder. Example: `/ship-plan codex` →
`.specs/planned/2026-07/codex/plan/`. Below, **`<dir>`** means the matched feature folder.

## Steps

1. **Validate.** Locate `<dir>` with `bash scripts/spec-locate.sh <name>`, and confirm
   `<dir>/plan/00-overview.md` exists. If the script finds no folder or it has no `plan/`, tell the user to run
   `/plan-tasks <name>` first (it generates this folder from an expanded spec) and stop.
   If it prints more than one line (a name collision), take the *first* — matches are
   ordered earliest-lifecycle-first, the active feature; the collision is auto-resolved
   by the suffix rule when it moves to `shipped/` in step 8.

2. **List remaining tasks** with `bash scripts/plan-next-task.sh <name>` — it prints the
   unimplemented `NN-*.md` task files in numeric order. Exit 2 means none remain: report
   the feature is fully implemented and stop here.

3. **Light re-grill — only if the plan drifted.** The plan was already grilled at
   `plan-tasks` time, so **do not** repeat that interview. Instead do a quick drift
   check: skim `<dir>/plan/` against `<dir>/expanded/` and the
   **Feedback & decisions** already in `resolution-log.md`, asking one question — has
   anything material shifted since the plan was grilled? (a task added / dropped /
   reordered, a binding decision reversed, or a fresh open question the implementer will
   hit head-on). If nothing material drifted, say so in one line and go straight to
   running. If something did, invoke the **`grilling`** skill but keep it **tight —
   only the drifted points, not the whole design** — resolve them with the user, fold
   the answers into the affected task files and `resolution-log.md`, then proceed. Never
   silently implement over a divergence.

4. **Run strictly sequentially.** Tasks are dependency-ordered by number, so never
   launch more than one at a time.

   > [!IMPORTANT]
   > **This loop runs straight through with no checkpoint between tasks.** If the user
   > wants to go one task at a time — to check quota, review each result, or resume a
   > partly-finished plan by hand — **do not use this skill**: launch `plan-implementer`
   > directly, once per task, and stop after each. That is the supported mode, not a
   > workaround. The agent self-discovers state (including `resolution-log.md`), so a
   > per-task prompt only needs the feature name and the task number — never a recap of
   > what earlier tasks built. Track progress from `plan/implemented/` +
   > `resolution-log.md`; don't hand-maintain a separate progress table.

   For the lowest-numbered remaining task:
   - Launch the `plan-implementer` agent (`Agent` tool, `subagent_type:
     "plan-implementer"`) with a prompt naming the feature and, if useful, the specific
     task number. The agent discovers prior progress itself — you do not need to recap
     what earlier tasks built. The agent defaults to Sonnet; if the task file or
     `00-overview.md` flags a task as especially tricky (broad refactor, subtle
     invariants, gnarly JS), pass `model: "opus"` on the `Agent` call for that task.
   - Wait for its completion notification.
   - Confirm the task's `.md` file moved to `plan/implemented/`.
   - If the agent reports failed verification or leaves the task file in place, **stop
     the loop immediately** and surface the failure to the user — do not start the next
     task on top of a broken one.
   - Otherwise, move to the next remaining task and repeat.

5. **Final sanity pass.** Once the loop empties, run this project's full test suite and
   linter once more (commands from `CLAUDE.md`'s Commands section) as a final check
   across everything the loop built. **If the feature has a UI/JS surface, a green suite
   is not enough** — apply the same runtime verification the `plan-implementer` agent
   uses: build the frontend, confirm the app serves the build (not a stale `public/hot`
   pointer), and drive the key flow via the **`run-imagoldfish`** skill (or hand the user
   the exact click-path). Frontend regressions routinely pass PHPUnit; see the feature's
   `resolution-log.md`.

6. **Consolidate the resolution log.** Ensure `<dir>/resolution-log.md` exists
   and captures the run's **deviations**, **issues → resolutions**, and
   **feedback/decisions** (the `plan-implementer` agent appends per task; fold in any
   feedback the *user* gave you during the loop, and any issue found in the final pass).
   This is the canonical, reproducible place these are recorded.

7. **Report** a summary: each task, what it built, its test counts, how any runtime
   surface was verified, and the deviations/issues the agents logged along the way (point
   at `resolution-log.md`).

8. **Stamp the source spec as shipped and move the folder** with
   `bash scripts/spec-advance.sh <name> shipped`. The script owns the mechanics —
   stamping `status: shipped` + `shipped: <date>`, applying the name-collision suffix
   rule from `.specs/README.md`, and `git mv`-ing the folder into the
   `.specs/shipped/<YYYY-MM>/` month bucket — and prints the final path.
   Run it before asking about the commit, so the stamp and
   the move ride in the implementation commit — the spec's git history then points at the
   commit that shipped it, no hash field needed. Only add a `commit: <short-hash>` line
   when the stamp is made *after* the implementation commit already exists (e.g. stamping
   retroactively), where the history link is lost.

9. **Ask before committing; then ship via the `ship-pr` skill.** Do not commit
   automatically — ask the user first (a visible, hard-to-reverse git action stays an
   explicit choice). Once confirmed, invoke the **`ship-pr`** skill, which owns the
   branch → commit (with CHANGELOG entry) → push → PR → auto-merge ritual — don't
   reimplement it here.

   One staging detail specific to this flow: step 8 **already moved** the feature folder
   to `.specs/shipped/<YYYY-MM>/<name>/`, so the old `.specs/planned/…` path no longer
   exists — staging it fails with `fatal: pathspec … did not match any files`. Stage the whole spec tree instead so git records the rename plus the
   new `plan/implemented/`, `expanded/`, and `resolution-log.md` files in one go:
   `git add -A .specs/` (the `.specs/` tree may be partly untracked, so `-A` is what
   captures the move + the new files). Then stage the actual source/test/doc changes.

## Notes

- This skill is the orchestration layer; all actual implementation happens inside
  `plan-implementer` agent runs, which are fully independent per task and self-discover
  state from the spec docs, `implemented/` folder, and working tree — don't duplicate
  that discovery work here.
- If the user only wants a single task run, or a pause between tasks, launch
  `plan-implementer` directly instead of this skill — see the callout in step 4.
