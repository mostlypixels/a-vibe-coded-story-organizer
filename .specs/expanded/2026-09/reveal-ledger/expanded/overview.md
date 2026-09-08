# Overview

The codex holds what is **true**. Nothing holds what the reader has been **told**.

"Marnborne owns the mines" sits in the codex from the first day of planning; readers learn
it in book 4. A writer eight books deep cannot ask which of her facts are still secret, so
she keeps a spreadsheet with a "revealed in" column in a second window — and it goes stale
the moment she reorders a chapter.

## Goals

- Mark the scene where the reader first learns a fact. A fact is a codex entry, one
  attribute value, or an event.
- Mark a fact as never revealed — the author knows, the reader never will.
- One page per book: what the reader knows by the end of it, and what is still hidden.
- Warn when a reveal points at a scene later than a scene that already leans on the fact.
- The reveal moves with the story. Reordering chapters must not silently make it wrong.

## Non-goals

- No per-character knowledge. This is what the *reader* knows, not what Raymondin knows.
- No degrees of reveal — hinted, implied, confirmed. One mark, one scene.
- No automatic detection of a reveal from prose. She marks it.
- No export of the ledger, and no reader-facing view.
- No new codex data. A reveal annotates a fact that already exists.

## The load-bearing constraint

The source spec says story order "already exists — `StoryNumbering` and the
`codex_scene_references_paging` sort key both do it. Reuse, do not re-derive."

**Half true, and the half that is missing is the feature.** `StoryNumbering` is
deliberately per-book: its docblock says "Numbering restarts at each book: Act 1 of book 2
is Act 1". This ledger's central question — "what does the reader know by the end of book
4?" — is a *project-wide* comparison across books.

Project-wide story order is an eight-key chain: `books.position, books.id, acts.position,
acts.id, chapters.position, chapters.id, scenes.position, scenes.id`. Nothing derives it
today. See [architecture.md](architecture.md).

## User stories

| As a | I want | So that |
| --- | --- | --- |
| Series planner, 8 books | to mark where a fact is first told | I stop keeping a spreadsheet |
| Series planner | the mark to survive a chapter reorder | the ledger does not rot as I revise |
| Series planner | one page of "known by end of book 4" | I can write book 5 without re-reading four books |
| Series planner | to mark a fact as never told | I can tell "not yet" from "not ever" |
| Series planner | a warning when a scene uses a fact before its reveal | I catch the leak before a reader does |

## Acceptance criteria

1. A codex entry, an attribute value or an event can be marked revealed in one scene.
2. The same can be marked **never revealed**, distinctly from unmarked.
3. Unmarked, revealed and never-revealed are three visible states, never conflated.
4. A page per book lists what the reader knows by the end of it and what is still hidden,
   counting every earlier book.
5. Reordering chapters, acts or books changes the ledger's answer with no write — the
   reveal points at a scene, and the scene's place is derived.
6. A fact whose reveal scene comes after a scene that references it is flagged.
7. A fact may carry at most one reveal.
8. A reveal's scene must belong to the same project as the fact.
9. Every route authorizes through the owning `Project`, and a non-owner gets 403.
