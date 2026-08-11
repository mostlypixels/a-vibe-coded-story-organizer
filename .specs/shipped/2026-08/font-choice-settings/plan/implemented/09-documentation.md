---
title: "Task 09 — Documentation"
---

# Task 09 — Documentation

## Scope

Document the feature for the next person: a compact section in
`documentation/architecture.md` linking to a `documentation/fonts.md` deep dive.

No code changes.

## Depends on

01–08 (document what shipped, not what was planned).

## Key decisions already made

* Entry point short, deep dive linked — the shape `revisions.md` already uses. The
  `architecture.md` section covers what it is, the load-bearing pieces, and the rules
  that bite; detail goes in `documentation/fonts.md`.
* The points worth writing down are the ones the code cannot say:
  * why config and not an enum, and why the slug is the only thing validated;
  * why the `{!! !!}` block is safe here for a *different* reason than the theme
    block's — no user input is interpolated at all;
  * why the JS preview needs its own copy of that rule;
  * `null` = "follow config", and why no column ever holds a default;
  * why the manuscript scale is relative rather than absolute;
  * why exports and the public share page do not follow the choice;
  * how to add a family: config entry → `scripts/fetch-fonts.sh` → `@font-face` → the
    drift test tells you which half you forgot.
* `CLAUDE.md` gets a *Font choice* note only if the feature adds a rule an agent would
  otherwise break — the "never a second path from a value into CSS" rule qualifies.
* The default change (Atkinson → Inter, no backfill) needs a `CHANGELOG.md` entry that
  says so plainly: existing users' look changes, and the fix is one setting.

## Consult

* `.claude/rules/documentation.md` → *Verbosity* — lists by default, prose only for why
* `documentation/revisions.md` — the entry-point/deep-dive split to mirror

## Tests to add

None. Run the docs consistency checks the suite already has.
