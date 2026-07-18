# Epub Configuration — Overview

## Problem statement

Today the EPUB export is **one button with zero choices**. `EpubExportController@store` takes
only a `project_id`; `EpubExporter::export()` renders a fixed shape:

- title page → in-book TOC → Act divider pages → Chapter pages (`"Chapter {n}: {name}"`) with
  scenes `<hr/>`-joined;
- **no** scene titles, **no** act/chapter/scene descriptions, **no** front-/back-matter
  (dedication, acknowledgements, preface, postface), **no** codex appendix;
- metadata (author / publisher / rights / isbn / cover) is emitted whenever the corresponding
  `Project` column is non-empty, with no way to suppress it;
- the chapter-title wording, the TOC depth, and the scene divider are all hard-coded.

Authors need to shape the book they actually publish. This feature adds a **per-project EPUB
configuration** and the export honours it.

It also reorganises the **Admin → Export & import** screen, which currently crams a `.zip`
export, an EPUB export, and Import into one Alpine-tabbed card, into three server-rendered pages.

## Scope of the split (from the spec's "Before proceeding")

The single tabbed page becomes a small section with **server-rendered sub-navigation** (links,
not Alpine tabs — one controller action per view):

- **Export**
  - **Export project** — the existing `.zip` export (StaticSiteExporter). Unchanged behaviour.
  - **Export ebook** — the EPUB export form **plus** the configuration this spec introduces.
- **Import** — the existing project import (upload + in-progress list). Unchanged behaviour.

## Goals

1. A persisted, per-project EPUB configuration the author edits once and re-uses on every export.
2. Author-supplied **front-/back-matter** (dedication, acknowledgements, preface, postface) as
   Markdown, saved with the project.
3. Include/exclude toggles for: project cover, scene titles, act/chapter/scene descriptions,
   author, publisher, rights, ISBN, and each front-/back-matter section.
4. Structural format choices: **chapter-title format**, **table-of-contents depth**, **scene
   divider style**.
5. An optional **codex appendix** (which entry types, with or without images).
6. Split the Export & import page into three server-rendered views as described above.
7. **Zero behaviour change for existing exports**: the default configuration reproduces today's
   output byte-for-byte where practical (same title page, same `"Chapter n: name"`, `<hr/>`
   dividers, no scene titles, no descriptions, no appendix).

## Non-goals (deferred to `epub-configuration-v2`)

The spec explicitly parks these — they must **not** be built here, and a follow-up draft spec
`epub-configuration-v2` should be created to carry them:

- **Per-scene before/after images.**
- **Chapter cover pages** — chapters have no image infrastructure today (no `cover_image`
  column, no upload UI). Adding it is a feature of its own. V1 covers **the existing project
  cover only** (see `open-questions.md` Q3).
- **A `Review` entity** (child of Project: title, link) and its appendix rendering.
- **Image-based scene dividers** (`divider_type = image`). V1 ships `hr` + `decorative` only
  (see `open-questions.md` Q6).

## User stories

- As an author, I open **Export ebook**, set my dedication and a preface, choose
  `"Chapter 12: The Storm"` as my chapter-title style and a decorative divider, save, and every
  future EPUB reflects it.
- As an author who hasn't configured anything, I still get a valid EPUB identical to today's.
- As an author, I suppress the ISBN and publisher from a proof copy without deleting those
  values from my project.
- As an author, I append my characters and locations as a reference section at the back of the
  book, with their portraits.

## Acceptance criteria

- **Split:** three routes/views exist; the sidebar's "Export & import" entry stays active across
  all three; sub-nav marks the current page with `aria-current="page"`; the `.zip` and Import
  flows behave exactly as before.
- **Config persistence:** saving the Export-ebook form persists an `EpubSetting` for the project
  (owner-only; a non-owner gets 403); reloading shows the saved values.
- **Markdown fields** validate with `ValidMarkdown`; invalid Markdown re-renders with
  `assertSessionHasErrors`.
- **Export honours config:** each toggle/enum measurably changes the packaged EPUB (asserted in
  `EpubExporterTest`), and the package still passes the existing well-formedness + OPF-schema
  gate.
- **Default = today:** a project with a freshly-defaulted `EpubSetting` produces the current
  output (guarded by an explicit regression test).
- **Authorization:** every new read/write walks `ProjectPolicy` (owner succeeds, non-owner 403),
  mirrored in each Form Request.
