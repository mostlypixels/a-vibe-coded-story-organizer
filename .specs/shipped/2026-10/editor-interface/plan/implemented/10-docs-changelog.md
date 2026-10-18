---
title: "Task 10 — Docs and changelog"
---

# Task 10 — Docs and changelog

## Scope

- New `documentation/features/editor.md`: routes, layout preference, `editor_cards` rules, linked-note defaults, what uses browser storage and why, the full-page scene switch and why.
- Links from `documentation/features/README.md` and the feature table in `documentation/architecture/README.md`.
- `documentation/features/rich-text.md`: the `sticky-toolbar` prop; codex candidates match aliases.
- `documentation/interface/components.md`: `x-editor-panel`, `x-editor-card`.
- One dated `CHANGELOG.md` section.
- Manual check with the run-imagoldfish skill (Playwright).

## Depends on

01 to 09.

## Key decisions already made

- Docs follow `.claude/rules/documentation.md` → Verbosity.
- Manual check on the Les Misérables demo: search speed, card fetch, three columns scroll alone, autosave then switch scene loses nothing, two tabs on one scene still lock, drawers at phone width.

## Tests

- The documentation link test passes.
- Manual check results go to `../resolution-log.md` only when something deviates.
