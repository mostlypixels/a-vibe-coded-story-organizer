# 13 — Archive version 4 and the `books/` reading layer

**Depends on:** 11.

## Scope

- `manifest.version` → **4**.
- `data/books/<id>-slug/book.json` plus its field files, cover, `publication-setting.json`, and
  `acts/<id>-slug/…` beneath it. `data/acts/` and `data/publication-setting.json` are gone.
- `data/project/project.json` slims to the project's own columns and gains `cover_file` +
  `data/project/cover/<name>`.
- `book.json` carries `language`, `author`, `publisher`, `isbn`, `overview_render_mode`,
  `rights_file` (as `rights.txt`, **not** `.html`), the four matter files and `cover_file`.
- The reading layer `book/` → `books/`: `books/index.html` lists the books,
  `books/NN/index.html` is one book's TOC, `books/NN/NN/NN.html` are the chapter pages.

**Not in scope:** reading version 4 back in — task 14.

## Key decisions

- **`chapterHref()` is unchanged** — it stays `%02d/%02d.html` relative to the book's own TOC, so
  the file-identity rule (raw `position`, never a derived number) survives and only the folder
  above it is new.
- **prev/next stay inside a book.** A book is a reading unit: the first chapter's *prev* and the
  last chapter's *next* both point at `../index.html`, that book's TOC.
- **`book.json.name` may be `null`** and must be written as `null`, not coerced. An unnamed book
  tracks its project's name.
- `data/` stays raw and lossless; `books/` remains the only place Markdown renders to HTML.
- This closes an existing gap: `language`, `author`, `publisher`, `rights`, `isbn` and the
  project cover were in **no** archive before, so the EPUB metadata never round-tripped.

## Consult

`expanded/export-import.md` → *`data/` layout*, *The `books/` reading layer*;
`documentation/export-format.md`.

## Tests

- `ExportTest`: the `data/books/…` layout, `publication-setting.json` inside the book folder,
  `books/index.html`, `books/NN/index.html`, `books/NN/NN/NN.html`.
- A two-book project exports both, in `position` order.
- An unnamed book writes `"name": null`.
- Every metadata field lands in `book.json` — the new coverage, not a port.
