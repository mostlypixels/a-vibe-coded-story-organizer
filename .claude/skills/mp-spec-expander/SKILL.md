---
name: mp-spec-expander
description: Expand a short feature spec from `.specs/<name>.md` into a folder of detailed specification and architecture documents. Use when the user runs `/mp-spec-expander <name>` or asks to expand/flesh out a spec file in the `.specs` folder.
---

# mp-spec-expander

Take a short feature specification and expand it into a set of detailed design documents.

## Argument

A single argument: the name of a Markdown file in the `.specs/` folder (with or without the `.md` extension). Example: `/mp-spec-expander plotline-merge` → reads `.specs/plotline-merge.md`.

## Steps

1. **Read the source spec.** Open `.specs/<name>.md`. If it's missing, list the `.md` files in `.specs/` and ask which one. Read `CLAUDE.md`, `.claude/guidelines.md`, and `documentation/` so expansions match this project's architecture and conventions.

2. **Explore relevant code.** Find the existing models, controllers, views, routes, and tests the feature touches. Ground every suggestion in what already exists — reference concrete files and patterns rather than inventing new ones.

3. **Create the output folder** `.specs/<name>/` (the file's base name, no extension).

4. **Write separate Markdown files** into that folder — one concern per file. Only include files that are relevant to the feature; a small feature may need just two or three. Typical set:
   - `overview.md` — expanded problem statement, goals, non-goals, user stories, acceptance criteria.
   - `data-model.md` — new/changed migrations, models, relationships, invariants, seeding impact.
   - `architecture.md` — controllers, routes (shallow-nesting convention), policies/authorization, where logic lives, service/support classes.
   - `ui.md` — Blade views and components to add/change, reuse of the `x-table`/icon component families, Alpine interactions.
   - `testing.md` — feature tests to add, edge cases, the main-plotline / position-ordering invariants to guard.
   - `open-questions.md` — ambiguities in the source spec and decisions to confirm.

5. **Keep each file focused and actionable.** Prefer concrete file paths, method names, and route names over prose. Flag anything that conflicts with an existing invariant (main plotline, position ordering, authorization-via-project).

6. **Report** the created folder and the list of files written, with a one-line summary of each.

## Notes

- Don't modify the original `.specs/<name>.md`.
- Don't implement the feature — this skill only produces specification/design documents.
- Match the project's documented conventions rather than introducing new architecture unless the spec demands it (call it out in `open-questions.md` if so).
