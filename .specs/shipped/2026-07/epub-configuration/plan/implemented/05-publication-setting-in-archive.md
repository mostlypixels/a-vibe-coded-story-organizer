# 05 — PublicationSetting travels in the archive

**Model:** Opus (untrusted-input validation + safe fallback; security-sensitive).

## Scope
The per-project config follows the project through a `.zip` export → import, under the manifest
version already bumped in task 02.

- `StaticSiteExporter`: serialize the project's `PublicationSetting` (its persisted attributes;
  omit/skip when the project has no row) into the `data/` descriptor. Raw values, never rendered.
- `ProjectImporter`: on import, **validate the config as untrusted input** (same enum/shape checks
  as `UpdatePublicationSettingRequest` — reuse the rule logic, don't duplicate loosely). On a
  malformed/absent config: **log/skip it, create the project with a default `PublicationSetting`,
  and still import all content** (config is a presentation preference — never fail the whole
  import over it). Extend `App\Support\ImportRules`/allow-list for the new descriptor entry.
- The `appendix_entry_types` must resolve against the archive's own codex types; drop unknowns
  rather than failing.

Does **not**: relax media security — forged files still reject the archive via the existing
`ArchiveValidator` content-sniff (chapter covers are covered in task 07). Does **not** render
anything.

## Depends on
02 (manifest bump + importer/exporter descriptor seam), 04 (the setting + its validation rules).

## Key decisions already made
Overview #7: config travels; malformed ⇒ default-and-continue, not reject. Untrusted-input posture
(CLAUDE.md) — validate every enum/bool/key on the way in.

## Docs to consult
`../expanded/open-questions.md` Q8, `../expanded/data-model.md` §"Import/export impact",
`documentation/export-format.md`, the import architecture in `documentation/architecture.md`.

## Tests to add
Round-trip: a project with a fully-customised `PublicationSetting` exports and re-imports **equal**.
A project with **no** setting round-trips to a default (no row / default instance). A deliberately
malformed config in a hand-built archive imports the **content** with default settings (assert no
exception, assert content present, assert default setting). Update `export-format.md` + `CHANGELOG`.
