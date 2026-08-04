# Testing

## `tests/Unit/StoryNumberingTest.php` (new)

The derivation's own contract — cheapest place to pin the edge cases.

- Two acts × two chapters → chapter numbers 1,2,3,4; scene numbers run unbroken across
  chapter and act boundaries.
- **Gap compaction:** delete the middle chapter of an act → the survivors number 1,2,3 even
  though `position` reads 1,3.
- **Act reorder renumbers:** swap two acts → the chapters that were 3,4 become 1,2, with no
  write to `chapters.position`.
- **Deterministic tie-break:** two siblings sharing a `position` number by `id`.
- `fromActs()` and `forProject()` produce identical maps for the same project.
- `fromActs()` fires zero queries (`DB::listen` / `assertQueryCount` style probe).
- Unknown id throws.

## `tests/Feature/ChapterTest.php`

- Index shows the continuous number, not the per-act position.
- **Regression for the `act_id` bug:** create acts A then B, move B above A, sort by `#` →
  B's chapters come first. Fails on today's `orderBy('act_id')`.
- Filtering by act does not renumber: filtered to act II, the first row still shows 3.

## `tests/Feature/SceneTest.php`

- Index `#` is the story-wide number; the new "In chapter" column shows `position`.
- Filtering by chapter leaves numbers project-wide.
- Move up/down still swaps `position` only (no new column written).

## `tests/Feature/StoryTest.php`

- TOC and headings show continuous chapter numbers across the act boundary.
- Scene numbers render and are continuous across chapters.
- Query count does not regress — the page still loads the tree once and builds the map from
  it (`fromActs`).

## EPUB (`tests/Feature/` epub export tests)

- Chapter headings and nav labels are continuous across act dividers, in every
  `ChapterTitleFormat`.
- Act headings and nav labels are continuous and gap-free after an act is deleted.
- A chapter with no scenes exports a heading-only page and **keeps its number**; an act with
  no chapters keeps its divider (open question #1).
- Chapter numbers in the EPUB equal the app's for the same project, including with
  placeholders between written chapters.
- Untitled scenes still read "Scene 1/2/3" within their chapter (open question #9).
- A project with acts and chapters but zero scenes is refused with today's message.
- Package filenames still `chapter-{id}.xhtml` — unchanged.
- Four existing tests invert: `test_it_drops_chapters_with_no_scenes`,
  `test_it_drops_acts_left_with_no_surviving_chapters`,
  `test_export_throws_epub_export_exception_when_the_tree_is_empty`, and the
  chapter-with-no-scenes case in `tests/Feature/EpubExportTest.php`.

## Static site export

- **Href regression:** with three acts and reordered chapters, `chapterHref` output is
  byte-identical to before the change (`01/01.html`, …).
- `data/` JSON still carries per-parent `position`; import round-trip
  (`ProjectGraphImporter`) unaffected.
- `book/index.html` TOC and chapter page headings show continuous chapter numbers — new
  output, since that layer showed titles only.
- Every `ChapterTitleFormat` drives the website output the same way it drives the EPUB.
- A project with no chapters still exports `book/index.html` and no chapter pages.

## Acts (`tests/Feature/ActTest.php`)

- The acts list `#` closes the gap left by a deleted act.
- The edit page hint shows the derived number and the sibling count.

## Public scene page

- `shared/scenes/show` renders the continuous chapter number for a chapter in act II.

## JS (`resources/js/scene-reorder.test.js`)

`moveScene` moves out of `app.js` into its own module first — `app.js` is the Vite entry and
pulls in Alpine and axios, and every existing JS test is per-module. It must keep assigning
`window.moveScene`; the story overview calls it from an inline `onclick`.

- A successful move swaps the two `data-scene-number` values along with the two sections, and
  leaves every other scene's number alone.
- A failed `PATCH` moves nothing and changes no number.
- End-of-chapter move buttons stay disabled after a reorder.

## Authorization

No new endpoints, no policy change — nothing new to cover. Existing 403 cases stand.
