# 04 — Docs + changelog

## Scope

- `documentation/architecture.md` — add a compact **Breadcrumbs** section: what it is (route-
  derived trail mirroring the nav), the load-bearing pieces (`Breadcrumbs`/`Crumb`,
  `x-breadcrumbs`, `x-page-heading`, the layout band + `header`-slot fallback), and the rules
  that bite: built off `routeProject` (not `project`, cross-ref `PageTitle`), section crumbs
  unlinked, the revisions `{entity}`+`{id}` exception, admin/non-project pages keep the header
  slot. Entry-point short; no re-explaining the code.
- `CHANGELOG.md` — one dated `## YYYY-MM-DD — Breadcrumbs (#PR)` section, 1–3 `Changed`/`Added`
  entries, user-visible only (breadcrumb navigation replaces the page-title bar). No class
  names/paths.
- Confirm `glossary.md` needs nothing new (breadcrumbs is a standard term); skip if so.

## Explicitly not in scope

- No code changes; docs only.

## Depends on

- 01, 02, 03.

## Key decisions

- Follow the project verbosity rules — lists, why-not-what, name files don't reproduce them.

## Tests

- None (docs). `SpecsStatusConsistencyTest` and existing suites already green from prior tasks.
