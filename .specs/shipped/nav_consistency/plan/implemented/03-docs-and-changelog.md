# Task 03 — Docs & changelog

## Scope

Record the change per the project's documentation guidelines. Small, no code.

1. **`CHANGELOG.md`** — under `## [Unreleased]`, add:
   - **Added** — active-state highlighting for the desktop primary-nav dropdowns (Timeline,
     Codex, Story) and their trigger buttons, with `aria-current="page"` on the active item.
   - **Changed** — `x-dropdown-link` now accepts an optional `active` prop; nav route-match
     expressions consolidated into a single source of truth shared by the desktop and responsive
     menus.

2. **`documentation/`** — add a short note (in `architecture.md` or `code-style.md`, wherever
   nav/components are already described) explaining:
   - `x-dropdown-link` mirrors `x-nav-link` / `x-responsive-nav-link`: pass `:active` to get the
     highlight + `aria-current`; default is off so existing menus are unaffected.
   - Nav active-state matchers live in one `@php` block at the top of the `navigation.blade.php`
     `@if ($project = …)` guard and are reused by triggers, desktop items, and the responsive
     menu — add new sections there, don't scatter inline `routeIs(...)`.
   - Use a `> [!NOTE]` callout for the "add new sections to the matcher block" tip.

## Explicitly NOT in this task

Any code/template/test change (done in Tasks 01–02).

## Depends on

**Task 01** and **Task 02** (documents their finished behavior).

## Key decisions already made

Keep a Changelog format, group under `## [Unreleased]`; GFM alert callouts for tips
(`documentation/` guideline).

## Docs to consult

`CLAUDE.md` → Documentation + Changelog sections; existing `documentation/architecture.md` for
placement and tone.

## Tests

None — documentation only. Verification is that `composer test` still passes (unchanged) and the
Markdown renders.
