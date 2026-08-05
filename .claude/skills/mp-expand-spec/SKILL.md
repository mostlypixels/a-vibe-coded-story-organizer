---
name: mp-expand-spec
description: Expand a short feature spec from `.specs/<status>/<name>/spec.md` into a set of detailed specification and architecture documents under that folder's `expanded/`, then move the folder to `.specs/expanded/`. Use when the user runs `/mp-expand-spec <name>` or asks to expand/flesh out a spec file in the `.specs` folder.
---

# mp-expand-spec

Take a short feature specification and expand it into a set of detailed design documents.

## Argument

A single argument: the feature name. A new hand-written spec sits flat in
`.specs/draft/<name>/`; stages past draft bucket features by month, at
`.specs/<status>/<YYYY-MM>/<name>/`. Locate the folder with
`bash scripts/spec-locate.sh <name>` (prints `<status><TAB><path>` per match,
earliest lifecycle first) — don't assume a fixed status.
Example: `/mp-expand-spec plotline-merge` → reads
`.specs/draft/plotline-merge/spec.md`.

## Steps

1. **Read the source spec.** Locate the feature folder with `bash scripts/spec-locate.sh <name>` and open its `spec.md`. If the script exits non-zero (no match), list the existing `.specs/**/spec.md` candidates and tell the user to create `.specs/draft/<name>/spec.md` first, then stop. **If it prints more than one line** (a name collision — e.g. a fresh `draft/<name>/` beside an already-shipped `shipped/<name>/`), take the *first* line — the script orders matches earliest-lifecycle-first, and that's the new, un-advanced work; the collision is auto-resolved by the suffix rule when this folder moves in step 6. Read `CLAUDE.md` and `documentation/` so expansions match this project's architecture and conventions. Below, **`<dir>`** means the matched feature folder.

2. **Explore relevant code.** Find the existing models, controllers, views, routes, and tests the feature touches. Ground every suggestion in what already exists — reference concrete files and patterns rather than inventing new ones.

3. **Create the output folder** `<dir>/expanded/` (the feature folder already exists — it holds `spec.md`).

4. **Write separate Markdown files** into `<dir>/expanded/` — one concern per file, in the style below. Only include files that are relevant to the feature; a small feature may need just two or three. Typical set:
   - `overview.md` — expanded problem statement, goals, non-goals, user stories, acceptance criteria.
   - `data-model.md` — new/changed migrations, models, relationships, invariants, seeding impact.
   - `architecture.md` — controllers, routes (shallow-nesting convention), policies/authorization, where logic lives, service/support classes.
   - `ui.md` — Blade views and components to add/change, reuse of the `x-table`/icon component families, Alpine interactions.
   - `testing.md` — feature tests to add, edge cases, the main-plotline / position-ordering invariants to guard.
   - `open-questions.md` — one line per question, each with your recommended answer.
     `mp-plan-tasks` runs the **`grilling`** skill over these docs, and this file is the
     grill's agenda: sharp and answerable, not hand-wringing.

5. **Write them short.** See *Writing style*. Flag anything that conflicts with an existing invariant (main plotline, position ordering, authorization-via-project).

6. **Stamp the status and move the folder** with
   `bash scripts/spec-advance.sh <name> expanded`. The script owns the mechanics —
   stamping `status: expanded` + `expanded: <date>` in the spec's frontmatter, applying
   the name-collision suffix rule from `.specs/README.md`, and `git mv`-ing the folder
   into the `.specs/expanded/<YYYY-MM>/` month bucket — and prints the final path; the
   possibly-suffixed name is what you pass to `mp-plan-tasks` next. The frontmatter stamps
   are the only edit ever made to the source spec's content. Lifecycle, one stamp + move
   per pipeline stage:
   `draft` → `expanded` (this skill) → `planned` (`mp-plan-tasks`) → `shipped` (`ship-plan`).

7. **Report** the created `expanded/` folder and location. Point the user at the next stage: `mp-plan-tasks <name>`, which will **grill** them on this design (`open-questions.md` first) before decomposing it into a plan.

## Writing style

Same rules as `.claude/rules/documentation.md` → Verbosity, applied to specs. These docs are read
by an agent under context pressure and by a user who won't read padding. No length budgets —
judge by padding, not word count.

- **Bullets and tables by default.** Prose only where reasoning genuinely needs it, a sentence
  or two. Never a paragraph restating the list beside it.
- **Every line load-bearing** — a decision, a constraint, a file to touch, a pitfall. If a line
  survives being deleted, delete it.
- **Don't restate the source spec, `CLAUDE.md`, or Laravel.** The expansion earns its place with
  what those don't say. Assume the reader knows the framework and the conventions.
- **Name the file, don't reproduce it.** Reference `app/Services/Foo.php` and move on. Code
  blocks only for contracts that don't exist yet (a signature, a config shape, a migration
  column list) — never to echo existing code or to show an obvious controller body.
- **Decide, don't survey.** State the choice. A rejected alternative gets one line, and only if
  it's the obvious thing someone would otherwise try. Genuine forks go to `open-questions.md`.
- **No scaffolding.** No preamble under a heading, no "this document covers…", no summary or
  recap section, no restating the next steps.
- **Fewer files.** A file that would mostly cross-reference another one shouldn't exist; fold it
  in. Only real overlap belongs in both places, and only once with a pointer.

## Notes

- Don't modify the original `spec.md`, except the frontmatter status stamp in step 6.
- Don't implement the feature — this skill only produces specification/design documents.
- Match the project's documented conventions rather than introducing new architecture unless the spec demands it (call it out in `open-questions.md` if so).
