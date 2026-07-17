---
name: draft-spec
description: Write a new feature specification as a draft under `.specs/draft/<name>/spec.md` with `status: draft` frontmatter — the stage-1 entry point of the .specs pipeline, before `/mp-spec-expander`. Use whenever the user asks you to write, draft, create, or start a spec (or a feature spec / short design doc) for this project, even in plain language ("write a spec for X"). Do NOT use it to expand, plan, or ship an existing spec — those are mp-spec-expander / plan-tasks / ship-plan.
---

# draft-spec

Author a short, hand-written feature spec and file it correctly as a **draft**. This is the
first stage of the `.specs/` lifecycle (`draft` → `expanded` → `planned` → `shipped`, see
`.specs/README.md`). A spec created anywhere other than `.specs/draft/<name>/spec.md`, or
without `status: draft` frontmatter, fails `tests/Unit/SpecsStatusConsistencyTest` and breaks
the pipeline — this skill exists so that never happens.

## Steps

1. **Understand the feature.** From the user's request (and a quick look at the code it
   touches — models, controllers, views, routes — grounded in `CLAUDE.md` and
   `documentation/`), work out the problem, the goals, and a
   rough approach. Ask the user only about genuinely blocking ambiguities; a draft is meant
   to be short and is stress-tested later by the `grilling` step in `plan-tasks`.

2. **Pick a name and scaffold.** Choose a short, descriptive `kebab-case` slug (e.g.
   `plotline-merge`), then run:

   ```
   php artisan spec:draft <name> --description="<one-line summary>"
   ```

   The command validates the name (kebab-case), checks it is **free across the whole
   tree** (a name reused anywhere — even under a shipped month bucket — fails
   `tests/Unit/SpecsStatusConsistencyTest`), and creates `.specs/draft/<name>/spec.md`
   with the correct `status: draft` frontmatter and title. If it reports a collision
   (typically a shipped feature you're following up), prefer a distinct new name; if the
   user insists on reuse, apply the collision suffix from `.specs/README.md` →
   *Name-collision handling* to the new slug and rerun.

3. **Write the spec body.** The command only scaffolds — replace everything below the
   `# <Feature title>` heading (the description or placeholder line) with the real
   content: a few short paragraphs covering the problem, the goals / non-goals, and a
   rough approach. Concrete but not exhaustive — the detailed design is generated later
   by `/mp-spec-expander`. Reference existing files and conventions rather than
   inventing new ones.

   Leave the frontmatter untouched: `status: draft` and the `.specs/draft/<name>/` location
   must always agree — that pairing is what the consistency test guards. Do not move the
   folder loose under `.specs/` or under any other status subfolder.

4. **Report** the created path and a one-line summary, then point the user at the next stage:
   `/mp-spec-expander <name>` to expand it into design docs.

## Notes

- This skill only writes the source `spec.md`. It does **not** expand, plan, or implement —
  those are `mp-spec-expander`, `plan-tasks`, and `ship-plan`.
- Keep the draft short. Its job is to capture intent; the pipeline fleshes it out.
