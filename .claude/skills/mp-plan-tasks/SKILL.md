---
name: mp-plan-tasks
description: Decompose an already-expanded feature spec (.specs/expanded/<name>/expanded/, the output of mp-expand-spec) into an ordered, dependency-tracked implementation plan under that folder's plan/, then move the folder to .specs/planned/ — the step between "expanded spec" and running /ship-plan or the plan-implementer agent. First grills the user on the expanded design (via the grilling skill) to resolve open questions before decomposing. Use when asked to plan, break down, or sequence an expanded spec into tasks.
---

# mp-plan-tasks

Turn an expanded feature spec into the plan that `/ship-plan` and the `plan-implementer` agent
run: a `00-overview.md` manual plus numbered `NN-*.md` task files. This skill writes the plan
only; implementation happens in `plan-implementer`, which serves every feature.

## Argument

The feature name. Find its folder with `bash scripts/spec-locate.sh <name>` — after
`mp-expand-spec` it sits at `.specs/expanded/<YYYY-MM>/<name>/`, with an `expanded/` subfolder
holding some of `overview.md`, `data-model.md`, `architecture.md`, `ui.md`, `testing.md`,
`open-questions.md`. On several matches, take the first line (the earliest lifecycle stage;
step 7 resolves the collision). No folder, or no `expanded/` → tell the user to run
`/mp-expand-spec <name>` first, and stop. Below, `<dir>` is the folder.

## Steps

1. **Read every doc in `<dir>/expanded/`,** and `CLAUDE.md`, so task boundaries match the
   project's real architecture.

2. **Grill the design.** Run the **`grilling`** skill over the whole expanded set — data model,
   architecture, UI and testing, with `open-questions.md` as the agenda. A design flaw costs
   far less to fix here than after the tasks exist.
   - Each answer goes into the plan you write next, and into **Feedback & decisions** once
     `resolution-log.md` exists (step 6).
   - An answer that changes which tasks exist, or their order, is binding on step 3.
   - The step is done when every open question has an answer and the user confirms shared
     understanding.

3. **Decompose into ordered tasks.** Each task is independently implementable and verifiable:
   a `plan-implementer` run can finish it, pass `scripts/verify.sh`, and move on. Order by
   dependency (data model before the UI that reads it).

   Size tasks here. A task with several interacting concerns — the one you would call "the
   heaviest slice" — becomes two or more ordered tasks now. Neither `ship-plan` nor
   `plan-implementer` can split a task mid-flight. If the natural boundary is unclear, ask
   the user while the grill is still open.

4. **Write `<dir>/plan/00-overview.md`,** the manual (never implemented or moved):
   - the execution order, one line of purpose per task;
   - the design defaults already decided, stated as binding;
   - the invariants every task preserves, taken from `data-model.md` and `architecture.md`
     (an ordering or uniqueness rule, the authorization pattern for new endpoints).

5. **Write one `<dir>/plan/NN-<slug>.md` per task:**
   - **Scope** — what this task builds, and what it defers, naming the later task that owns it.
   - **Depends on** — task numbers that must be in `plan/implemented/` first.
   - **Key decisions already made** — binding choices, so the implementer does not re-decide
     them.
   - **Docs** — the `<dir>/expanded/*.md` sections to consult.
   - **Tests** — what this task adds.

6. **Scaffold `<dir>/resolution-log.md`,** so every feature logs in the same place:

   ```markdown
   # <Feature> — resolution log

   Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
   implementing this feature. Read it before extending the feature.

   An exception log: a task that went to plan gets no entry, because the diff and the task
   file already record what was built. Bullets under the headings below, root cause first.

   ## Feedback & decisions

   _None yet._

   ## Deviations from the spec/plan

   _None yet._

   ## Issues → resolutions

   _None yet._
   ```

7. **Advance the spec** with `bash scripts/spec-advance.sh <name> planned`. It stamps
   `status: planned` and `planned: <date>` (nothing else in the file), applies the collision
   suffix rule from `.specs/README.md`, `git mv`s the folder to `.specs/planned/<YYYY-MM>/`, and
   prints the final path. The printed name is what `ship-plan` takes next.

8. **Report** the plan's location, the task list one line each, and any open question left
   unanswered because it did not block decomposition.

## Writing style

A task file is a brief for an implementer that also reads the expanded docs: it points, and
states what is binding at its altitude. `.claude/rules/documentation.md` → Verbosity applies.

- **Bullets and tables.** Prose only for a decision's *why*, a sentence or two.
- **Link, don't copy:** `see expanded/data-model.md → Ordering`.
- **Assume `CLAUDE.md` and Laravel are loaded.** "Add a feature test, owner + 403" is enough.
- **Code only for contracts that do not exist yet** — a signature or a column list. The
  implementer writes the bodies.
- **Scope by boundary:** what is in, what is deferred and to which task.
- **Start with content** — each section opens on its first bullet and ends on its last.
