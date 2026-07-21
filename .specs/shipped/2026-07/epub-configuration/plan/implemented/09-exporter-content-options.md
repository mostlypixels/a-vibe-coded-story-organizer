# 09 — Exporter: content options (titles, descriptions, dividers)

**Model:** Opus (rich-HTML→XHTML normalization edge cases on the well-formedness boundary).

## Scope
Per-content rendering choices, and the shared rich-HTML→XHTML helper they require.

- **`RichText::toXhtmlFragment(?string $html): string`** — new helper beside `toPlainText()`:
  `DOMDocument::loadHTML` (UTF-8, no doctype/html/body wrappers) → `saveXML` of the body fragment,
  producing well-formed XHTML from sanitized rich HTML. Introduced here, reused by task 13.
- `renderChapter()` / `chapter.blade.php`:
  - Heading from `ChapterTitleFormat::format($position, $name)` (the enum is the single source —
    replace the hard-coded string here **and** in `chapterNavTitle()`).
  - `include_scene_titles` ⇒ render `Scene.name` as a per-scene heading.
  - `include_chapter_descriptions` ⇒ render `Chapter.description` (rich HTML → `toXhtmlFragment`)
    under the heading; `include_scene_descriptions` ⇒ `Scene.description` above each scene body.
  - Scenes interleaved with the chosen `DividerType` HTML instead of the hard-coded `<hr/>`.
  - Branch in Blade on flags passed via view data — no `match` on raw strings in the service.
- `renderAct()` / `act.blade.php`: `include_act_descriptions` ⇒ render `Act.description`
  (→ `toXhtmlFragment`).
- `styles.css`: a `.divider` ornament rule for `decorative`.

Empty content + toggle on ⇒ render **nothing** (no blank heading/element).

Does **not**: change TOC/nav depth (task 10), front/back matter (task 11), covers/appendix.

## Depends on
08 (settings threaded + regression guard in place).

## Key decisions already made
Overview #3 (defaults keep today's shape), #6 (shared `toXhtmlFragment`), enums own formatting.
Descriptions are rich HTML, not Markdown — must go through the helper to stay well-formed.

## Docs to consult
`../expanded/architecture.md` §B, `../expanded/ui.md` §"New EPUB Blade views".

## Tests to add
Extend `EpubExporterTest`: each `ChapterTitleFormat` yields the expected heading **and** matching
nav label; name-less chapter has no dangling `": "`. Scene titles / act|chapter|scene descriptions
on ⇒ present, off ⇒ absent, empty ⇒ no blank element. `decorative` swaps `<hr/>` for the ornament,
still well-formed. A description containing deliberately non-XHTML sanitized HTML (unclosed/void
tags) still passes `validatePackage()` (proves `toXhtmlFragment`). Defaults===v1 still green.
