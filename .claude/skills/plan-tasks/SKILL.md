---
name: plan-tasks
description: Decompose an already-expanded feature spec (.specs/expanded/<name>/expanded/, the output of mp-spec-expander) into an ordered, dependency-tracked implementation plan under that folder's plan/, then move the folder to .specs/planned/ — the step between "expanded spec" and running /ship-plan or the plan-implementer agent. First grills the user on the expanded design (via the grilling skill) to resolve open questions before decomposing. Use when asked to plan, break down, or sequence an expanded spec into tasks.
---

# plan-tasks

Turn an expanded feature spec into the `00-overview.md` + numbered `NN-*.md` task-file
plan that `/ship-plan` and the `plan-implementer` agent consume.

## Argument

A single argument: the feature name. Locate the feature folder with the glob
`.specs/*/<name>/` — after `mp-spec-expander` runs it sits at `.specs/expanded/<name>/` —
and it must contain an `expanded/` subfolder holding whichever of `overview.md`,
`data-model.md`, `architecture.md`, `ui.md`, `testing.md`, `open-questions.md` are
relevant. If no folder matches or it has no `expanded/`, tell the user to run
`/mp-spec-expander <name>` first (on a `.specs/draft/<name>/spec.md` source spec) and stop.
If the glob matches more than one folder (a name collision), work on the one in the *earliest*
lifecycle stage — that's the active feature; the collision is auto-resolved by the suffix rule
when this folder moves in step 8. Below, **`<dir>`** means the matched feature folder.

## Steps

1. **Read every doc in `<dir>/expanded/`.** Read `CLAUDE.md` too, so task boundaries
   match this project's real architecture.

2. **Grill the design before decomposing.** The expanded docs are a design that has
   never been stress-tested against the user. Invoke the **`grilling`** skill (via the
   `Skill` tool) on `<dir>/expanded/` — especially `open-questions.md` — and
   walk the user through it one question at a time until you reach shared understanding.
   `grilling` is the single source of the grill behavior; don't reimplement it here.
   Feed the grill from the whole expanded set (data model, architecture, UI, testing),
   not just the open questions, since a design flaw surfaced here is far cheaper to fix
   than after tasks are written. Two things to carry forward:
   - Fold every resolved decision into the plan you're about to write (and, once
     `resolution-log.md` exists in step 6, record them under **Feedback & decisions**).
   - Any grill answer that changes *which* tasks exist or their order — not just
     fine-grained detail within a task — is binding on the decomposition below. Don't
     guess on plan-shaping questions; that's exactly what the grill is for.

   Do not proceed to decomposition until the user confirms the grill has reached shared
   understanding.

3. **Decompose into an ordered sequence of tasks.** Each task should be independently
   implementable and independently testable — a `plan-implementer` run against it
   should be able to finish, verify, and move on without needing a task not yet done.
   Order by dependency, not just narrative flow (data model before the UI that reads
   it, etc.).

4. **Write `<dir>/plan/00-overview.md`** — the manual, never itself
   implemented or moved. Include:
   - The execution order and a one-line purpose for each task.
   - The design defaults already decided in the spec docs, stated as binding (later
     tasks must not re-litigate them).
   - The feature's core invariants every task must preserve — pull these out of
     `data-model.md`/`architecture.md` (e.g. an ordering/uniqueness invariant, an
     authorization pattern every new endpoint must follow).

5. **Write one `<dir>/plan/NN-<slug>.md` per task**, each containing:
   - Scope: exactly what this task builds, and what it explicitly does **not** (name
     the later task that owns the deferred part).
   - Depends on: task numbers that must be in `plan/implemented/` first.
   - Key decisions already made: binding choices from the spec docs, so the
     implementer doesn't re-decide them.
   - Which `<dir>/expanded/*.md` docs to consult for detail.
   - The tests this task should add.

6. **Scaffold the resolution log.** Create `<dir>/resolution-log.md` with the
   three empty headings the pipeline fills in — this fixes *where* issues, resolutions,
   and feedback get documented so it's the same for every feature:

   ```markdown
   # <Feature> — resolution log

   The running record of feedback/decisions, deviations from the spec/plan, and
   issues → resolutions found while implementing and verifying this feature. The
   `plan-implementer` agent appends here per task; `ship-plan` consolidates it. Read it
   before extending the feature.

   ## Feedback & decisions

   _None yet._

   ## Deviations from the spec/plan

   _None yet._

   ## Issues → resolutions

   _None yet._
   ```

7. **Do not generate a per-feature implementer agent.** A generic `plan-implementer`
   agent (`.claude/agents/plan-implementer.md`) already runs any feature's plan by
   taking the feature name as an argument — don't recreate a bespoke one.

8. **Stamp the status and move the folder.** Set `status: planned` in the YAML frontmatter
   of `<dir>/spec.md` (the lifecycle is `draft` → `expanded` → `planned` → `shipped`;
   `mp-spec-expander` added the frontmatter — if it's missing, add it). Touch nothing
   else in that file. Then move the whole feature folder to `.specs/planned/<name>/` so
   its location matches the stamp (use `git mv`; create `.specs/planned/` if absent).
   **Before the `git mv`, apply the name-collision suffix rule** from `.specs/README.md` →
   *Name-collision handling*; the possibly-suffixed name is what you pass to `ship-plan` next.

9. **Report** the created `plan/` folder, the folder's new location under `.specs/planned/`,
   the task list with a one-line summary of each, and flag any open question you left
   unresolved because it didn't block decomposition.

## Notes

- Don't implement any task's code — this skill only produces the plan folder.
- Keep task files scoped tightly enough that `plan-implementer` can verify each one
  with the project's test suite before moving to the next; a task that can't be
  verified in isolation should be split.
