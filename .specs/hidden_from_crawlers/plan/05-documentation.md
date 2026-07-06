# Task 05 — Documentation

## Scope

Bring the docs in sync (guideline: docs track architecture/workflow changes).
Verified by inspection, not the test suite (docs-only task).

- `documentation/architecture.md` — new "Hidden from crawlers" section:
  - Singleton `CrawlerSetting` (global, read via `current()`, lazily seeded from
    `config/crawlers.php`).
  - Dynamic `/robots.txt` route + `RobotsTxtGenerator` (product-token allow-groups,
    whole-site block); **static `public/robots.txt` was removed** — call this gotcha
    out explicitly.
  - `x-robots-meta` component; forced on the `public` (shared-scene) layout.
  - **The authorization exception:** this is the one setting not owned by a
    `Project`, so it does **not** use `ProjectPolicy`'s walk — any authenticated
    user may edit it. Use a `> [!WARNING]` callout so nobody "fixes" it into a
    project walk.
- `CLAUDE.md` — a short paragraph matching the density of the existing feature
  notes, covering the singleton, the dynamic robots route + removed static file,
  the meta component, and the authorization exception.
- `CHANGELOG.md` — `## [Unreleased]` / `### Added`: hidden-from-crawlers feature
  (robots.txt + noindex meta, admin toggle + user-agent whitelist).

## Explicitly NOT in this task

No code changes — reference only what tasks 01–04 built.

## Depends on

Tasks 01–04.

## Key decisions already made (binding)

All of `00-overview.md`'s binding decisions; document them, don't revisit.

## Consult

All of `expanded/`, and the final state of the code from tasks 01–04.

## Tests

None (documentation only). Verification: re-read the updated docs for accuracy
against the shipped code; confirm the full suite from tasks 01–04 is still green.
