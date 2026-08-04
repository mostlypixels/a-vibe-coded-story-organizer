# Continuous numbering — overview

## Problem

`position` does double duty: sort key *within a parent* **and** the number shown to the
reader. So chapter numbering restarts at 1 in every act, and scene numbering restarts in
every chapter. Novels don't number that way.

Two consequences fall out of the same conflation:

- Deleting a chapter leaves a hole (`position` is never compacted), so the list shows
  "1, 2, 4".
- `ChapterController::index` orders the `#` column by `act_id`, not by the act's
  `position` — reorder two acts and the "sorted by #" list is in the wrong order.

## Goals

- Chapters numbered 1..N across the whole project, in act order.
- Scenes numbered 1..N across the whole project, in act → chapter order.
- Scenes keep a visible per-chapter position (a new column on the scenes list).
- One derivation, shared by every display site: story overview, chapters/scenes lists,
  public scene page, EPUB (headings + nav/TOC), static-site book layer.

## Non-goals

- **No schema change.** No `number` column, no backfill, no renumber-on-reorder.
- `position` semantics are untouched — still per-parent, still gappy, still the reorder key.
- ~~Act numbering rules unchanged.~~ Acts *are* rank-derived too (`open-questions.md` #6):
  they never restarted, but deleting one left the same gap chapters had.
- The archive export format is untouched: `position` stays in the JSON payload
  (`StaticSiteExporter` data layer, `documentation/export-format.md`).
- Static-site chapter hrefs (`%02d/%02d.html`, act position / per-act chapter position)
  are untouched — they are file identity, not display.

## User stories

- Writer opens the Story Overview: Act II's first chapter reads "Chapter 3", not "Chapter 1".
- Writer opens the Scenes list: `#` is the story-wide scene number; a second column says
  which scene it is inside its chapter.
- Reader opens the EPUB: chapter numbers run unbroken across act dividers.

## Acceptance criteria

- Chapter/scene numbers are 1..N with no gaps and no restarts, everywhere they appear.
- Reordering an act renumbers every downstream chapter and scene, with no write to
  `position` beyond the swap that already happens.
- Deleting a chapter closes the numbering gap it leaves.
- Sorting the chapters list by `#` orders by act *position*, not `act_id`.
- Static-site chapter **URLs** and the archive JSON are byte-identical to before. The
  website's contents page and chapter headings are not — they gain numbers they never had.
- Exports omit nothing: a chapter with no scenes exports as a heading-only page, so a book's
  chapter numbers always match the app's and never shift as placeholders get written.
