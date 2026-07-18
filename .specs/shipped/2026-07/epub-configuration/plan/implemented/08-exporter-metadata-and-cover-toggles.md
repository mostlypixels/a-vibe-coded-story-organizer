# 08 — Exporter: thread the setting + metadata/cover toggles + defaults===v1

## Scope
Wire `PublicationSetting` into `EpubExporter` for the **first time**, covering only the toggles
that gate existing output, and lock the "unchanged by default" guarantee.

- `EpubExporter::export()` reads `$project->publicationSettingOrDefault()` once and threads that
  `PublicationSetting $settings` object through the private methods (pass the object, not 20 bools).
- `applyMetadata()`: gate each optional block behind its toggle — `include_author && filled(...)`,
  same for publisher, rights, isbn. Title, language, the primary URN identifier, and accessibility
  metadata stay unconditional.
- `applyCover()`: gate on `$settings->include_project_cover` (read bytes via `CoverImageService`
  from task 06).

Does **not**: change chapter titles, scene titles, descriptions, dividers, TOC depth, front/back
matter, chapter covers, or the appendix (later tasks). Those defaults keep today's behaviour.

## Depends on
01 (setting), 04 (setting persisted so a real row can be exercised), 06 (`CoverImageService`).

## Key decisions already made
Overview #3: **defaults===v1**. Metadata/cover toggles default `true`, so a default setting emits
exactly what today emits.

## Docs to consult
`../expanded/architecture.md` §B "method-by-method", current `EpubExporter::applyMetadata/applyCover`.

## Tests to add
Extend `EpubExporterTest` (unzip + assert on entries; `validatePackage()` runs inside `export()`,
so a schema regression fails automatically):
- **Regression (highest priority):** a project with a default/absent setting produces the current
  output — `"Chapter n: name"`, `<hr/>` dividers, no scene titles/descriptions/front-matter/
  appendix, metadata present when the columns are set. This test guards every later exporter task.
- `include_author=false` (author set) ⇒ OPF has **no** `dc:creator`; same for publisher/rights.
- `include_isbn=false` ⇒ no `urn:isbn:` identifier, but the generated URN stays.
- `include_project_cover=false` (cover present) ⇒ no cover manifest item; title/URN/a11y always
  present.
