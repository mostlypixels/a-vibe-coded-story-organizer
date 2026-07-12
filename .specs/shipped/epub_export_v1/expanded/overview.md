# Epub export (v1) — overview

## Problem

The export page (`Admin → Export & import`, `resources/views/admin/data/index.blade.php`)
currently produces one artifact: a `.zip` containing a lossless `data/` copy and a
human-readable `book/` HTML reading layer (see `documentation/export-format.md`). Authors who
want a standard e-reader file (Apple Books, Kobo, Kindle-via-conversion, etc.) have no way to
get one. This feature adds a second, independent export format — a real `.epub` file — to the
same page.

## Goals

- A new "Epub export" section on the existing Export tab, alongside (not replacing) the
  current `.zip` export.
- The generated file is a structurally valid EPUB 3 document: proper container, OPF package
  document, nav document (TOC), and per-act/per-chapter XHTML content documents.
- Content structure mirrors the existing `book/` reading layer's content rules (act → chapter →
  scene, `<hr>`-joined scenes, position-ordered, no scene titles) but repackaged as EPUB
  XHTML/OPF/NCX instead of loose HTML files.
- Typographic cleanup specific to EPUB: smart dashes, ellipses, and quotes.
- Baseline EPUB accessibility metadata and correct `lang` attributes.
- Enough commercial metadata (cover, publisher, rights, ISBN) that an author could plausibly
  submit the file to a retailer, without building DRM or retailer-specific packaging.
- A lightweight, PHP-native structural validation pass on the generated files, plus a pointer
  to the official `epubcheck` tool for authors who want full conformance verification.

## Non-goals (v1)

- DRM of any kind.
- Retailer-specific submission formats (KDP-specific metadata, Apple Books-specific
  requirements beyond standard EPUB 3).
- Running the real `epubcheck` (Java) as part of the request — see `architecture.md` §Validation.
- Import: this is export-only, symmetric with how `book/` is export-only today.
- Changing how scenes render anywhere else in the app (editor, Story overview, share page,
  existing `book/` export) — the epub's smart-typography pass is isolated to epub generation.
- Fiction-specific epub features beyond what's specified: no embedded fonts, no illustrations
  in the manuscript body, no audio/video/interactive content.

## User story

As a project owner, from `Admin → Export & import → Export`, I fill in optional book metadata
on my Project (language, author, cover image, publisher, rights text, ISBN), then click a new
"Download EPUB" button next to the existing zip export. I get back a `.epub` file I can load
directly into an e-reader app, with acts as divider pages, chapters as their own pages, one
table of contents, and clean prose typography.

## Acceptance criteria

- The Export tab has a distinct "Epub export" section with its own submit action; the existing
  zip export form and behavior are untouched.
- Downloading produces a `.epub` file that opens without error in at least one real EPUB reader
  (manual verification — see `testing.md`).
- Table of contents lists every surviving Act, each with its surviving Chapters nested
  underneath, in `position` order.
- Every Act divider page reads "Act {position}" plus the Act's `name` (name line omitted if
  blank); no Act `description` is rendered.
- Every Chapter page reads "Chapter {position}: {name}" followed by its Scenes' rendered
  Markdown, `<hr>`-joined, no per-scene titles; no Chapter `description` is rendered.
- Chapters with zero Scenes are omitted from the epub entirely (no page, no TOC entry). Acts
  left with zero surviving Chapters are likewise omitted entirely.
- If a project has no exportable content after the above skip rules, the export request fails
  validation with a clear message instead of producing a file.
- `--`/`---` become en/em dashes, `...` becomes an ellipsis, straight quotes become curly quotes
  — inside the epub only.
- Every generated XHTML document declares `lang` from `Project.language`; the OPF declares
  EPUB accessibility metadata (`schema:accessibilityFeature`, `accessMode`,
  `accessibilitySummary`).
- The OPF declares `dc:title` (`Project.name`), `dc:language`, `dc:creator` (`Project.author`,
  when present), `dc:identifier` (generated URN), `dc:publisher` (when present), rights
  (when present), and the ISBN (when present, as a second `dc:identifier` with an
  `opf:scheme="ISBN"` attribute alongside the URN).
- The export page shows a short note with a link to
  https://www.w3.org/publishing/epubcheck/ recommending authors validate the file themselves.
