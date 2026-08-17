# Export & import

`data/` gains a book level, so this is a **breaking layout change**:
`manifest.version` → **4**, and `ImportRules::SUPPORTED_MANIFEST_VERSIONS = [4]`. Version 3 is
rejected outright, the same call the feature already made for 1 and 2 — pre-V1, nobody holds an
archive they cannot re-export. (Accepting 3 by folding `data/acts/` into a single seeded book is
[Q4](open-questions.md).)

## `data/` layout

```
data/manifest.json                { version: 4, project_id, exported_at, includes_media }
data/tags.json
data/word-count-snapshots.json    project-level, unchanged
data/project/
  project.json                    { id, name, daily_word_goal, total_word_goal,
                                    description_file?, cover_file? }
  description.html
  cover/<name>                    NEW (bytes only when media is included)
data/books/<id>-slug/
  book.json                       (see below)
  description.html
  dedication.md  acknowledgements.md  preface.md  postface.md
  cover/<name>
  publication-setting.json        moved out of data/ root — it is per book
  acts/<id>-slug/
    act.json                      { id, name, position, book_id, description_file? }
    chapters/<id>-slug/ …         unchanged below this line
data/timeline/…                   unchanged (project-scoped)
data/codex/…                      unchanged (project-scoped)
```

`book.json`:

```json
{
  "id": 4, "name": "The Long Winter", "position": 1, "project_id": 42,
  "language": "en", "author": "…", "publisher": "…", "isbn": "…",
  "overview_render_mode": "chapter",
  "description_file": "description.html", "rights_file": "rights.txt",
  "dedication_file": "dedication.md", "acknowledgements_file": "acknowledgements.md",
  "preface_file": "preface.md", "postface_file": "postface.md",
  "cover_file": "cover/front.jpg"
}
```

`name` is **nullable** and must round-trip as `null` — an unnamed book tracks its project's
name, and coercing it to a string on export or import materializes the value and breaks that.
It is the one key here that may legitimately be `null` rather than absent.

The field-file and null-handling rules are unchanged: a null/empty field omits **both** the file
and its `*_file` key. `rights` is a `text` column but plain, not rich — write it as
`rights.txt`, never `.html`, so the importer's sanitizer gate does not treat it as a fragment.

> [!NOTE]
> **This closes an existing gap.** `language`, `author`, `publisher`, `rights`, `isbn` and the
> project cover are in no archive today — `addProject()` never wrote them, so the EPUB metadata
> silently did not round-trip. Moving them onto `book.json` (plus `project/cover/`) is the
> moment to fix that, and the round-trip test should assert every one of them.

Both cover files are plain path columns with no declared mime, so `ArchiveValidator`
content-sniffs them on bytes alone (`finfo` + `getimagesize` must agree), exactly like the
chapter cover.

`ImportRules::ALLOWED_DIRECTORIES` becomes `data/project/`, `data/books/`, `data/timeline/`,
`data/codex/`, `books/`; `data/acts/` and `book/` are removed.
`ALLOWED_FILES` loses `data/publication-setting.json`.

## Import

- **Books import inside the existing `story` phase.** Do not add a fifth `ImportPhase` case —
  the phase list is a stored checkpoint contract, and books own acts, which is what `story`
  already means. Add a `books` id map alongside `acts`/`chapters`/`scenes`.
- **The seeded book is reconciled, never duplicated.** Creating the `Project` fires
  `Project::booted()`, which now seeds one book beside the main plotline and the Start/End
  bookends. `ProjectGraphImporter` **updates that row in place** with the archive's
  lowest-`position` book and maps its id onto it, then creates the rest. This is the identical
  rule the main plotline and the bookends already follow, and missing it is how a one-book
  import ends up with two books.
- `position` replays verbatim from JSON on books as on everything else — never re-derived from
  directory order.
- `publication-setting.json` keeps its "untrusted, never fatal" posture, now **per book**: a
  malformed config logs, skips that book's config, and imports the content anyway.

## The `books/` reading layer

`book/` is renamed **`books/`** and gains one level, per the source spec's
`books/booknumber/actnumber/…`:

```
books/index.html          NEW — every book, linking its TOC
books/NN/index.html       one book's table of contents (the old book/index.html)
books/NN/NN/NN.html       book position / act position / per-act chapter position
```

`StaticSiteExporter::chapterHref()` is **unchanged** — it stays `%02d/%02d.html` relative to the
book's own TOC, so the file-identity rule (raw `position`, never a derived number) survives
untouched and only the folder above it is new. Prev/next links stay **within a book**: a book is
a reading unit, so the first chapter's *prev* and the last chapter's *next* both point at
`../index.html`, that book's TOC.

## EPUB

`EpubExporter::export(Book $book): string`. Everything the exporter reads off the project today
— metadata, cover, the four matter pages, `PublicationSetting`, the language — reads off the
book. `EpubExportRequest` validates and authorizes `book_id` (walking `$book->project` for the
policy, keeping the "the admin gate is not ownership" rule intact). The filename slugs the
book name. `EpubExportException` ("nothing to export") is now per book, so a project with one
empty book still exports its other books.

Two things stay project-scoped inside a book export:

- **The codex appendix** draws on the shared project codex, **filtered to the entries this
  book's own scenes reference** (`scene_codex_entry` joined to `Book::sceneQuery()`, then the
  `appendix_entry_types` filter). Unfiltered, book 2's appendix lists book 3's characters — a
  spoiler in a published file. A book that references nothing gets **no appendix pages**, never
  the full codex.
- **Scene event references** resolve against project-wide events (under the recommended answer to
  [Q1](open-questions.md)).

Rename `include_project_cover` → `include_book_cover` on `publication_settings` and in the
archive descriptor; `bookTree()` → `actTree()`.

> [!IMPORTANT]
> `EpubExporterTest`'s `defaults_v1_regression` compares a full generated package. It **will**
> fail on this change and must be re-baselined deliberately — a single book carrying the same
> metadata must still produce byte-identical output once OPF timestamps are normalised. If it
> does not, something moved that should not have.
