# 05 — Update documentation and changelog

## Scope

Update every doc that names the PHP/Laravel version so it matches what actually landed, and add
the changelog entry. No app code changes.

## Depends on

Task 03 (need the final, confirmed version numbers before documenting them).

## Key decisions already made

* `documentation/architecture.md:3` currently reads `"This is a Laravel 12 app..."` — confirmed
  during expansion as needing the update to Laravel 13 (and PHP 8.5 if the runtime version is
  mentioned nearby).
* Grep `README.md` and `melusine.md` for a version mention too — none was found during expansion,
  but re-check in case something was added since.
* `CHANGELOG.md`: add an entry under `## [Unreleased]` → `### Changed`, per this project's
  Keep-a-Changelog convention (see `CLAUDE.md` → Changelog).

## Docs to consult

* `expanded/architecture.md` — "Documentation to update after both bumps land".

## Tests

No PHPUnit test — this is a docs-only task. Verify by re-grepping for `Laravel 12` / `8.2` across
`documentation/`, `README.md`, `melusine.md` and confirming no stale reference remains.
