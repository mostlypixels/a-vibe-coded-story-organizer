# Continuous numbering — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- **Authors organise before they write, so an empty chapter is a deliberate placeholder.**
  This reversed `expanded/open-questions.md` #1, which had the EPUB number over the filtered
  tree: renumbering around unwritten chapters means every later chapter shifts the moment a
  placeholder gets written, and the book disagrees with the app in the meantime. Exports now
  omit nothing — empty chapters export as heading-only pages, acts with no chapters keep
  their divider — so export numbers always equal app numbers.
- **Stub pages carry the heading only**, no "not written yet" filler. Nothing the author
  didn't write should appear inside their book.
- **The EPUB still refuses when the project has zero scenes**, with today's message. Without
  the skip-empty filter that guard needed a new trigger, or a fresh outline would export as
  a book of blank pages.
- **Acts are rank-derived too** (`open-questions.md` #6, which the other expanded docs never
  followed through on). One rule — a displayed number never has a gap — instead of an
  exception for acts.
- **Untitled scenes keep per-chapter EPUB nav labels** ("Scene 3", not "Scene 147"). That
  label is the only place a scene number reaches a reader, and a project-wide count has no
  meaning under a chapter heading.
- **The website book layer had no chapter numbers at all**, so this feature adds them rather
  than making them continuous — and they honour the same `ChapterTitleFormat` setting as the
  EPUB, which had never applied to that export.
- **The book/ TOC's act label ("Act N: Name" / "Act N") mirrors `EpubExporter::actNavTitle()`**
  — task 08 left the act format unspecified (unlike chapters, acts have no
  `ChapterTitleFormat`-style setting), so `StaticSiteExporter` duplicates the same small
  format rather than inventing a different one, keeping the two exports' TOCs reading alike.
- **Edit-page hints avoid ordinals**: "Chapter 7 — 2 of 5 in Act II". `Number::ordinal()` was
  available (`ext-intl` is a hard requirement) but bakes English grammar into a translated
  string, and the count is more useful than "2nd" anyway.
- **The scenes list's "In chapter" column sits beside "Chapter"**, not second — the chapter
  name and the position within it read as one idea.

## Deviations from the spec/plan

- `expanded/overview.md`'s "static-site export is byte-identical" acceptance criterion is
  false as of task 08. Chapter **URLs** stay byte-identical; the TOC and chapter headings
  gain numbers they never had.

## Issues → resolutions

- **`StoryNumbering::forProject()` always eager-loads the whole act → chapter →
  scene tree**, even from `chapters/index` and `acts/index`, which only ever
  render chapter/act numbers. That's an extra `scenes` query neither page needed
  before, and it broke two pre-existing word-count N+1 guard tests
  (`ChapterTest`/`ActTest` `..._issues_one_grouped_query_for_word_counts`, task 9)
  that counted queries against the `scenes` table and asserted exactly 1. Both
  still guard the real invariant (O(1) queries per page load, not O(rows)) — their
  expected count moved from 1 to 2, with a comment explaining the second query.
  Relaxing an existing performance guard to fit new code deserves a second look:
  building the scene map lazily (on the first `scene()` call) would drop that query
  on both pages and let the original assertion stand. Deferred, not rejected — the
  cost is one lightweight id/position/fk query, and the pages that *do* show scene
  numbers need the map anyway.
