---
name: plan-tasks
description: Decompose an already-expanded feature spec (.specs/<name>/, the output of mp-spec-expander) into an ordered, dependency-tracked implementation plan under .specs/<name>/plan/ — the step between "expanded spec" and running /ship-plan or the plan-implementer agent. Use when asked to plan, break down, or sequence an expanded spec into tasks.
---

# plan-tasks

Turn an expanded feature spec into the `00-overview.md` + numbered `NN-*.md` task-file
plan that `/ship-plan` and the `plan-implementer` agent consume.

## Argument

A single argument: the feature name — a folder `.specs/<name>/` must already exist
(typically produced by `mp-spec-expander`), containing whichever of `overview.md`,
`data-model.md`, `architecture.md`, `ui.md`, `testing.md`, `open-questions.md` are
relevant. If `.specs/<name>/` doesn't exist, tell the user to run
`/mp-spec-expander <name>` first (on a `.specs/<name>.md` source spec) and stop.

## Steps

1. **Read every doc in `.specs/<name>/`.** Read `CLAUDE.md` and `.claude/guidelines.md`
   too, so task boundaries match this project's real architecture.

2. **Resolve blocking ambiguity first.** If `open-questions.md` has items that would
   change *which* tasks exist or their order (not just fine-grained detail within a
   task), ask the user via `AskUserQuestion` before decomposing. Don't guess on
   plan-shaping questions.

3. **Decompose into an ordered sequence of tasks.** Each task should be independently
   implementable and independently testable — a `plan-implementer` run against it
   should be able to finish, verify, and move on without needing a task not yet done.
   Order by dependency, not just narrative flow (data model before the UI that reads
   it, etc.).

4. **Write `.specs/<name>/plan/00-overview.md`** — the manual, never itself
   implemented or moved. Include:
   - The execution order and a one-line purpose for each task.
   - The design defaults already decided in the spec docs, stated as binding (later
     tasks must not re-litigate them).
   - The feature's core invariants every task must preserve — pull these out of
     `data-model.md`/`architecture.md` (e.g. an ordering/uniqueness invariant, an
     authorization pattern every new endpoint must follow).

5. **Write one `.specs/<name>/plan/NN-<slug>.md` per task**, each containing:
   - Scope: exactly what this task builds, and what it explicitly does **not** (name
     the later task that owns the deferred part).
   - Depends on: task numbers that must be in `plan/implemented/` first.
   - Key decisions already made: binding choices from the spec docs, so the
     implementer doesn't re-decide them.
   - Which `.specs/<name>/*.md` docs to consult for detail.
   - The tests this task should add.

6. **Do not generate a per-feature implementer agent.** A generic `plan-implementer`
   agent (`.claude/agents/plan-implementer.md`) already runs any feature's plan by
   taking the feature name as an argument — don't recreate a bespoke one.

7. **Report** the created `plan/` folder, the task list with a one-line summary of
   each, and flag any open question you left unresolved because it didn't block
   decomposition.

## Notes

- Don't implement any task's code — this skill only produces the plan folder.
- Keep task files scoped tightly enough that `plan-implementer` can verify each one
  with the project's test suite before moving to the next; a task that can't be
  verified in isolation should be split.
