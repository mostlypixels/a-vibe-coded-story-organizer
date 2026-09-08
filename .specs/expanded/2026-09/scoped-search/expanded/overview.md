# Overview

## Problem

Search always searches everything. At 400 chapters a common word hits ~1400 scenes, each
column caps at 5, and *See all 1400 results* is a cliff. The writer knows which book,
roughly where, and what kind of thing — the form asks for none of it.

## Where the filtering must happen

`ProjectSearch::searchEntity()` runs `$query->get()` and then matches **in PHP** (accent
folding is not portable to SQL). So a filter applied after matching saves nothing: the
rows were already hydrated, `Scene.contents` included. Every filter in this feature belongs
on the Builder in `ProjectSearch::queryFor()`, or on the decision to skip a domain
entirely.

That is the single constraint the whole design hangs off.

## Goals

- Book filter. Hidden when the project has one book.
- Chapter range, in **story order** — not a position `BETWEEN`, which is meaningless
  across acts (`position` is per-parent and gappy).
- Domain filter: which of the eight columns run at all.
- Filters ride the URL, survive into the domain page, and the back button works.
- Column counts reflect the filters.

## Non-goals

Matching semantics, saved searches, ranking, a full-text index, the list-page search bars.

## Acceptance criteria

- A book filter narrows Acts, Chapters and Scenes, and hides Plotlines, Events and the
  three codex columns rather than showing them empty.
- A chapter range narrows Chapters and Scenes; Acts narrows to the acts those chapters
  belong to.
- An unchecked domain runs no query at all — assertable by query count.
- *See all :count* counts the filtered set, and the link carries every filter.
- The domain page and its `page=` keep the filters; so does *Back to search*.
- A book or chapter id from another project fails validation.
