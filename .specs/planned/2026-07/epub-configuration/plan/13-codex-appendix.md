# 13 — Codex appendix

**Model:** Opus (heaviest surface; multiple interacting concerns; must stay schema-valid).

## Scope
The final, heaviest slice: an optional back-matter appendix of codex entries. Independently
shippable — nothing else depends on it.

- Gate on `include_codex_appendix` **and** a non-empty `appendix_entry_types`. Load the project's
  `codexEntries()` filtered to those types, ordered by (`type`, `name`); eager-load `media` when
  `appendix_include_images`.
- Render (via new Blade views under `resources/views/exports/epub/`): an appendix section
  heading/cover page, then one page per entry (`appendix-entry.blade.php`) — entry name heading,
  optionally the **first** media image (embedded via `CoverImageService`/`Storage` + the library
  image API; missing file skipped), and the entry `description` run through
  `RichText::toXhtmlFragment()` (task 09) so the rich HTML is well-formed XHTML.
- Emit at the `appendix` slot in the `section_order` walk (task 11). Add appendix entries to the
  TOC/nav at the appropriate depth.

Does **not**: embed all images (first only — V2), include a `Review` entity (V2), or add
appendix-specific config beyond the three fields already on the setting.

## Depends on
09 (`toXhtmlFragment`), 11 (the `appendix` slot in the ordered walk), 04 (appendix config
persisted). Run last.

## Key decisions already made
Overview #6 (rich HTML via the shared helper), appendix in v1 as the final task, **first image
only** per entry.

## Docs to consult
`../expanded/architecture.md` §B "codex appendix", `../expanded/open-questions.md` Q7, `CodexEntry`/
`CodexMedia` models.

## Tests to add
Extend `EpubExporterTest`: only the selected types appear, ordered; `appendix_include_images`
embeds the first image (and a missing file is skipped, export still validates); an entry
description with deliberately non-XHTML sanitized HTML still validates (proves the helper);
appendix off or no types ⇒ no appendix. Package validates. Defaults===v1 still green. Update
`documentation/architecture.md` (exporter/appendix) + `CHANGELOG.md` for the whole feature.
