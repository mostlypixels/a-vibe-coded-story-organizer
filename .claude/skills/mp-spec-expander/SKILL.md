---
name: mp-spec-expander
description: Expand a short feature spec from `.specs/<status>/<name>/spec.md` into a set of detailed specification and architecture documents under that folder's `expanded/`, then move the folder to `.specs/expanded/`. Use when the user runs `/mp-spec-expander <name>` or asks to expand/flesh out a spec file in the `.specs` folder.
---

# mp-spec-expander

Take a short feature specification and expand it into a set of detailed design documents.

## Argument

A single argument: the feature name. The folder lives under a status subfolder as
`.specs/<status>/<name>/` (a new hand-written spec sits in `.specs/draft/<name>/`) and
contains a `spec.md` source file. Locate it with the glob `.specs/*/<name>/` — don't assume
a fixed status. Example: `/mp-spec-expander plotline-merge` → reads
`.specs/draft/plotline-merge/spec.md`.

## Steps

1. **Read the source spec.** Locate the feature folder with the glob `.specs/*/<name>/` and open its `spec.md`. If no folder matches, list the existing `.specs/*/*/spec.md` candidates and tell the user to create `.specs/draft/<name>/spec.md` first, then stop. **If the glob matches more than one folder** (a name collision — e.g. a fresh `draft/<name>/` beside an already-shipped `shipped/<name>/`), operate on the one in the *earliest* lifecycle stage — that's the new, un-advanced work; the collision is auto-resolved by the suffix rule when this folder moves in step 6. Read `CLAUDE.md` and `documentation/` so expansions match this project's architecture and conventions. Below, **`<dir>`** means the matched feature folder.

2. **Explore relevant code.** Find the existing models, controllers, views, routes, and tests the feature touches. Ground every suggestion in what already exists — reference concrete files and patterns rather than inventing new ones.

3. **Create the output folder** `<dir>/expanded/` (the feature folder already exists — it holds `spec.md`).

4. **Write separate Markdown files** into `<dir>/expanded/` — one concern per file. Only include files that are relevant to the feature; a small feature may need just two or three. Typical set:
   - `overview.md` — expanded problem statement, goals, non-goals, user stories, acceptance criteria.
   - `data-model.md` — new/changed migrations, models, relationships, invariants, seeding impact.
   - `architecture.md` — controllers, routes (shallow-nesting convention), policies/authorization, where logic lives, service/support classes.
   - `ui.md` — Blade views and components to add/change, reuse of the `x-table`/icon component families, Alpine interactions.
   - `testing.md` — feature tests to add, edge cases, the main-plotline / position-ordering invariants to guard.
   - `open-questions.md` — ambiguities in the source spec and decisions to confirm.
     The next stage (`plan-tasks`) runs the **`grilling`** skill over these expanded
     docs before decomposing, so make each open question a sharp, answerable prompt
     (state your recommended answer) rather than vague hand-wringing — it becomes the
     grill's agenda.

5. **Keep each file focused and actionable.** Prefer concrete file paths, method names, and route names over prose. Flag anything that conflicts with an existing invariant (main plotline, position ordering, authorization-via-project).

6. **Stamp the status and move the folder.** Set `status: expanded` in YAML frontmatter at
   the top of `<dir>/spec.md` (add the frontmatter block if the file has none; a spec
   without frontmatter is implicitly `draft`). Then move the whole feature folder to
   `.specs/expanded/<name>/` so its location matches the stamp (use `git mv`; create
   `.specs/expanded/` if absent). **Before the `git mv`, apply the name-collision suffix
   rule** from `.specs/README.md` → *Name-collision handling*; the possibly-suffixed name
   is what you pass to `plan-tasks` next. The frontmatter stamp is the
   only edit ever made to the source spec's content. Lifecycle, one stamp + move per pipeline stage:
   `draft` → `expanded` (this skill) → `planned` (`plan-tasks`) → `shipped` (`ship-plan`).

7. **Report** the created `expanded/` folder, the folder's new location under `.specs/expanded/`, and the list of files written, with a one-line summary of each. Point the user at the next stage: `plan-tasks <name>`, which will **grill** them on this design (`open-questions.md` first) before decomposing it into a plan.

## Notes

- Don't modify the original `spec.md`, except the frontmatter status stamp in step 6.
- Don't implement the feature — this skill only produces specification/design documents.
- Match the project's documented conventions rather than introducing new architecture unless the spec demands it (call it out in `open-questions.md` if so).
