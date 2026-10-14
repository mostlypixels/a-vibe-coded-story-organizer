# 05 — Import

## Scope

- Record a `notes` ID map in the Notes phase of `ProjectGraphImporter`.
- New `ImportPhase::Links` after `Notes`. It walks every rich field and every `Scene.contents` of the new project and calls `EntityLinks::rewrite()` with `$idMaps`. An old id with no map entry unwraps to its text.
- Then `EntityLinkIndex::syncProject()`. One transaction for the phase.
- Update `documentation/export-import/archive-format.md`: the `entity:` form, archive ids, and the dangling-id rule.
- Deferred: exports → 06.

## Depends on

- 04.

## Key decisions already made

- A dangling id is not an import error, unlike a structured note link. Text can cite a target that was deleted before the export.
- Export writes the text as stored. It needs no change.
- Rewrite with `saveQuietly()` or a direct update, then sync once. One sync per row is slow on a large project.

## Docs

- `expanded/architecture.md` → Import.

## Tests

- Links in a note body, a codex description and scene Markdown point at the new ids after import, for each target type.
- A dangling id becomes plain text.
- Resume after a failure in the Links phase gives the same result.
- Export then import of the seed project keeps the seed link and its index row.
