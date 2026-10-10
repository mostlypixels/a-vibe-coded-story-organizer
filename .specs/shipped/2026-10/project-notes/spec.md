---
status: shipped
shipped: 2026-10-10
planned: 2026-10-10
expanded: 2026-10-10
---

# Project Notes

A writer keeps a lot of text that is not the story and not a codex entry: research, a long
synopsis, continuity problems, decisions for the whole book. Today the app has nowhere to put
it. Only scenes have a notes field, so book-wide material is lost or forced into the wrong place.

The Gardenia Girl `.ai` folder shows the scale: about 30 such pages, some over 100 KB.

## Notes vs codex

One question decides: does the name appear in a scene?

* Yes → codex. People, places, groups, in-world lore. They have aliases and the matcher links them.
* No → note. Research, synopsis, continuity, to-dos. They are *about* the story, not *in* it.
* Both (a real London street you researched) → a codex entry for the street, a note for the
  research, and a link between them.

Notes get no aliases, matcher, attributes or timeline. That keeps them from becoming a weaker codex.

## Goals

* A **Notes** menu item per project: any number of notes, each a title and a long rich-text body.
* **Links, many to many.** A note links to zero or more:
  * books, acts, chapters, scenes;
  * events, plotlines;
  * codex entries.
* A note with no links is a general note (research, to-do).
* Each linkable entity shows a **Notes** card: its notes, "New note", "Link a note".
* **Categories, like folders:**
  * a note is in one category or none;
  * categories nest (Research → London → Places);
  * categories belong to the project and sort by name.
* The Notes page shows the category tree beside the note list. Filters: category, linked
  entity type, book, text search.
* Notes are part of the project like any other content:
  * searchable with the existing project search;
  * covered by revisions and autosave;
  * carried in project export and archive import.
* Long notes get a table of contents built from their headings.
* The scene `notes` field moves into this system: one note per non-empty field, linked to its
  scene. Then the column goes. Pre-V1 data is demo only, so a destructive migration is fine.

## Starter categories

Created for each new project. The writer can rename or delete them. Top level only.

* **Planning** — synopsis, outline, scene map
* **Research** — history, places, society, language, sources
* **Continuity** — conflicts, open questions, decisions to lock
* **Plot threads** — setups and payoffs, rumours, mysteries
* **Publishing** — style guide, reader reports, release checklists
* **Ideas** — loose ideas, cut material, "maybe later"

Gardenia Girl mapping: `synopsis.md` and `scene-map.md` → Planning; `historical-accuracy/*` →
Research; `conflicts.md` → Continuity; `setups-and-payoffs.md` and `rumours-and-conspiracy.md`
→ Plot threads; `publication/*` → Publishing.

## Rules

* Delete a linked entity → remove the link, keep the note.
* Delete a non-empty category → move its notes and sub-categories up one level. Nothing is lost.
* Category depth has no data limit. The interface may cap it (about 3 levels).
* Authorization walks from the note or category to its `Project` and `ProjectPolicy`.

## Non-goals

* No file attachments or images beyond what the rich-text editor already allows.
* No tags on notes. Categories and links cover the need.
* No sharing, comments or collaboration. Solo use first.
* Notes never appear in the EPUB or static-site export.
* No change to codex `description` fields.
* No AI features: no generated summaries or research.

## Rough approach

* Follow the codex pattern: a project-owned resource with its own nav entry, index and edit
  page (`documentation/features/codex.md`).
* Links: one polymorphic pivot (`notables`: `note_id`, `notable_type`, `notable_id`).
* Categories: `parent_id` on a `note_categories` table.
* Reuse the rich-text field, autosave and revisions as they are
  (`documentation/features/rich-text.md`, `documentation/features/revisions.md`).
* Add notes as a new project search domain.
* The planned codex containment tree (`.scratchpad/advanced_codex_notes.md`) is also a tree. If
  both ship, share the tree code then, not before.

## Open questions

* Rich HTML or Markdown body. Imported AI notes are Markdown; converting once at import is the
  likely answer.
* "Plot threads" may overlap plotlines once plotlines get a status. It may go later.
* The shelved `author-notes` spec (notes on scenes) is covered by this one. Close it when this
  ships.
