# 04 — Epub packaging

## Scope

- `composer require rampmaster/phpepub` (PHP 8.2+, LGPL 2.1 — confirmed during planning as
  the maintained fork; not `grandt/phpepub`, which is dead).
- Extend `EpubExporter` (from task 03) with a packaging step that takes the filtered/rendered
  tree and produces an actual `.epub` file at a temp path:
  - Two-level TOC/nav: Acts as parent nav entries, their surviving Chapters nested underneath,
    in `position` order (built via the library's chapter/nav API, not hand-written XML).
  - Metadata mapped onto the library's setter methods: title (`Project.name`), language
    (`Project.language`), author (`Project.author`, only when set), publisher
    (`Project.publisher`, only when set), rights (`Project.rights`, only when set), a primary
    identifier generated as `urn:imagoldfish:project:{id}` (always present), and — when
    `Project.isbn` is set — a **second** identifier with an ISBN scheme.
  - Accessibility metadata via the library's native methods (`setAccessibilitySummary()`,
    `addAccessMode()`, `addAccessibilityFeature()`) rather than hand-written OPF XML —
    confirmed available on `rampmaster/phpepub` during planning.
  - Cover image: when `Project.cover_image` is set, read the file off the `public` disk and
    pass it to the library's cover-image API.
  - The CSS file from task 03 is attached so every chapter/act document references it.
  - Output: a path to a generated `.epub` file in a temp location (mirror
    `StaticSiteExporter`'s temp-file lifecycle/cleanup pattern).

## Explicitly not in scope

- Structural validation of the generated file and the empty-tree exception (task 05).
- The HTTP layer / controller (task 06).

## Depends on

01 (needs the metadata columns) and 03 (needs the filtered/rendered content to package).

## Key decisions already made

- `rampmaster/phpepub` is the confirmed library choice (see `00-overview.md` binding
  decisions) — do not swap libraries mid-implementation without flagging it in
  `resolution-log.md`.
- The ISBN, when present, is a **second** `dc:identifier` (with an ISBN scheme), not a
  replacement for the generated URN identifier — both must be present.
- No DRM, no retailer-specific packaging — out of scope entirely, not deferred to a later
  task within this feature.

## Docs to consult

- `expanded/architecture.md` — the full metadata-to-OPF mapping table.
- `expanded/data-model.md` — which `Project` fields are nullable and therefore
  conditionally included.
- `rampmaster/phpepub`'s own README/API (fetched during planning) for exact method names —
  verify against the actually-installed version, since method names may have shifted between
  releases.

## Tests

Extend `tests/Unit/Services/EpubExporterTest.php` (or a new `EpubExporterPackagingTest`):
- A project with all optional metadata set (`author`, `publisher`, `rights`, `isbn`, cover)
  produces a `.epub` file whose OPF (unzip and inspect, or use the library's own read-back if
  available) contains each corresponding field, plus both identifiers.
- A project with all optional metadata null produces a valid `.epub` whose OPF contains only
  title, language, and the generated URN identifier — no empty/placeholder values for the
  omitted fields.
- The TOC nav is two-level: each surviving Act entry has its surviving Chapters nested under
  it, in `position` order.
- Cover image, when set, is embedded in the manifest and referenced as the cover.
