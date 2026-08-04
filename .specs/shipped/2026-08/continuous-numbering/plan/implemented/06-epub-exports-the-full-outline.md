# 06 — EPUB exports the full outline

## Scope

`EpubExporter` stops dropping content. Authors organise before they write, so an empty
chapter is a deliberate placeholder — dropping it made every later chapter's number shift the
moment that placeholder got written.

- `filteredTree()` no longer filters: drop the skip-empty pass and rename it `bookTree()`.
  Update its docblock, the two `{@see}` references, and the class docblock's invariant list
  (which currently documents skip-empty as a hard rule).
- A chapter with no scenes exports as a **heading-only page** — its number and title, nothing
  else, no app-written filler text.
- An act with no chapters keeps its **divider page**.
- The `nothingToExport` guard moves from "the filtered tree is empty" to "the project has no
  scenes anywhere". Message and controller handling unchanged.

Not in scope: the numbers on those pages (07).

## Depends on

Nothing.

## Key decisions

- This aligns the EPUB with the website export, which already publishes empty chapters as
  heading-only pages — that side needs no change here.
- Verified safe: a zero-scene chapter renders `<section class="chapter"><h1>…</h1></section>`,
  which is well-formed, so `validatePackage()` passes untouched.
- `bookTree()` is public and called at ~20 sites in `tests/Unit/Services/EpubExporterTest.php`
  — a mechanical rename. The real work is the four tests that **invert**:
  `test_it_drops_chapters_with_no_scenes`,
  `test_it_drops_acts_left_with_no_surviving_chapters`,
  `test_export_throws_epub_export_exception_when_the_tree_is_empty`, and the
  chapter-with-no-scenes case at `tests/Feature/EpubExportTest.php:219`.
- Reverses `expanded/open-questions.md` #1, which had the EPUB number over the filtered tree.
  That answer predates the placeholder framing; see `resolution-log.md`.

## Tests

- A chapter with no scenes exports a page carrying its heading and no body.
- An act whose chapters are all empty still exports its divider.
- A project with acts and chapters but zero scenes is refused, with today's message.
- A project with one scene and nine empty chapters exports all ten chapter pages.
- Package filenames are still `chapter-{id}.xhtml` / `act-{id}.xhtml`.
- The package still validates (XHTML well-formedness + OPF schema).
