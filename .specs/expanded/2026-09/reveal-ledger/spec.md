---
status: expanded
expanded: 2026-09-08
---

# Reveal Ledger

The codex holds what is true. It has no way to hold what the reader has been told. "Marnborne
owns the mines" sits in the codex from the first day of planning, but readers do not learn it
until book 4 — and a writer eight books deep cannot ask the app which of her facts are still
secret. She keeps a spreadsheet with a "revealed in" column open in a second window while she
writes, and it goes stale the moment she reorders a chapter.

This is the question that decides whether a late book lands. See `.scratchpad/series-planner.md`.

## Goals

- Mark the scene where the reader first learns a fact. A fact is a codex entry, one
  attribute value, or an event.
- Mark a fact as never revealed — the author knows, the reader never will.
- One page per book (or up to a book): what the reader knows by the end of it, and what is
  still hidden.
- Warn when a reveal points at a scene later than a scene that already leans on the fact.
  Rough is fine; the writer decides.
- The reveal moves with the story. Reordering chapters must not silently make it wrong.

## Non-goals

- No per-reader or per-character knowledge. This is what the *reader* knows, not what
  Raymondin knows.
- No degrees of reveal — hinted, implied, confirmed. One mark, one scene.
- No automatic detection of a reveal from the prose. She marks it.
- No export of the ledger, and no reader-facing view of it.

## Rough approach

- One polymorphic `reveals` table: the revealed thing, the scene, and a nullable note. A
  null scene with a "never" flag covers the secret case.
- "By the end of book N" is a story-order comparison, and story order already exists —
  `StoryNumbering` and the `codex_scene_references_paging` sort key both do it. Reuse, do
  not re-derive.
- The read side is one screen in the style of the codex read views (shipped
  `codex-entry-read-view`): two lists, known and hidden, filtered by a book selector.
- Open: an attribute value is already a timeline row ("hair colour, from The Second
  Curse"). A reveal on it is a second time axis on the same row. Does the attribute
  timeline show both, or does the ledger stay a separate screen?
- Open: the warning needs to know which scenes lean on a fact. `scene_codex_entry` gives
  the mentions, but a mention is not a use. Probably good enough; probably noisy.
