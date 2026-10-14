---
status: planned
planned: 2026-10-14
expanded: 2026-10-14
---

# Entity Links

## Problem

A writer cannot link from text to a scene, chapter, codex entry or other part of the project. Notes imported from Manuskript link to `.md` files that do not exist here. A link that holds a database ID or URL breaks on export and import, because the importer gives every row a new ID.

## Goals

- Links inside every rich text field (notes, scene text, summaries, codex descriptions) to any type in `NoteLinkType`.
- Store a typed reference (`data-link="scene:42"`), not a URL. Build the URL when the page renders.
- Links survive export and import: one rewrite pass after the last import phase, which uses the existing ID maps in `ProjectGraphImporter`.
- A link to a deleted target shows as plain text, marked as missing.
- Backlinks: "what links here" on each target, from a table that is filled when the text is saved. Follow the `SceneReferenceMatcher` pattern.
- EPUB and static site exports show plain text for these links.
- The Manuskript importer resolves `.md` links to the new entities. A link with no match becomes plain text.

## Non-goals

- Links between projects.
- Stable UUIDs on models.
- Links by title, or automatic links from names in the text.
- Links that work in EPUB or static site exports.
- Links to a position inside a scene.

## Rough approach

- Rename `NoteLinkType` to a name that is not only about notes (`LinkTargetType`, or similar). It stays the one list of link target types.
- Allow `data-link` in `HtmlSanitizer` and the import `ContentSanitizer`. Check that the target belongs to the same project.
- Add a link picker to the rich text editor. Reuse the note link picker.

## Open questions

- The Manuskript import is on the unmerged `manuskript-import` branch. Merge it first, or do that part after?
- Do structured note links (the pivot) stay separate from links in the body, or become one feature?
- How does the editor show a missing link while the writer edits?
