# Archive format

[Documentation](../README.md) › [Export and import](README.md) › Archive format

This is the compatibility contract for project export and import. An archive contains:

- **`README.md`** — project information and directions for readers. It is not a source of truth.
- **`books/`** — a human reading version. It is not a source of truth.
- **`data/`** — the lossless machine-readable project.

> [!IMPORTANT]
> `data/` is **raw and lossless**. Every field file carries the **exact stored column
> value** — never re-rendered, re-sanitized, or reformatted. Only the `books/` layer
> renders Markdown to HTML. Do not blur the two.

## What lives where

The archive mirrors the app's ownership split, so where a field sits in `data/` tells you who
owns it:

| Branch | Holds | Scope |
|---|---|---|
| `data/project/` | name, the two word goals, description, the dashboard cover | the project |
| `data/books/<id>-slug/` | one book: publication metadata, matter pages, cover, publication setting, and its `act → chapter → scene` tree | per book |
| `data/timeline/` | plotlines and events | the project, shared by every book |
| `data/codex/`, `data/tags.json` | codex entries, attribute definitions, tags, media | the project, shared by every book |
| `data/word-count-snapshots.json` | the writing history | the project |

## Stable identifiers

Every entity's identity is its **database primary key**, written into its JSON and
used for every cross-reference (`event_id`, `chapter_id`, `plotline_ids`, …). An
import remaps these ids to freshly-inserted rows. Directory-name slugs (`<id>-slug`)
are **cosmetic** — never rely on a slug or filename for identity.

## `data/manifest.json`

The archive's root descriptor, written once per export:

```json
{
  "version": 4,
  "project_id": 42,
  "exported_at": "2026-08-17T14:03:11+00:00",
  "includes_media": true
}
```

| Key              | Type    | Meaning                                                                 |
|------------------|---------|-------------------------------------------------------------------------|
| `version`        | integer | The `data/` format version. An import reads this to decide how to interpret the archive. |
| `project_id`     | integer | The exported project's database primary key.                            |
| `exported_at`    | string  | ISO 8601 timestamp of when the export was produced.                     |
| `includes_media` | boolean | Whether media **bytes** were copied into the archive (the "Include images & files" toggle). Media **metadata** is written regardless; this flag records whether the bytes are present. |

### The `version` contract

`version` is bumped **only** on a breaking change to the `data/` layout — a renamed or
removed field, a changed directory scheme, a changed relationship encoding. Purely
additive changes (a new optional field, a new entity type folder) do **not** bump it;
an importer must ignore keys it does not recognize.

> [!IMPORTANT]
> **Only version 4 is supported.** `ImportRules::SUPPORTED_MANIFEST_VERSIONS = [4]` — every
> older archive is rejected outright. Version 4 moved the manuscript under
> `data/books/<id>-slug/acts/`, gave each book its own `publication-setting.json`, and renamed
> the reading layer `book/` → `books/`; a relocated path is exactly the breaking change this
> number exists for. There is no migration path between versions: pre-V1, nobody holds an
> archive they cannot simply re-export.

Revision history is not exported. It is large, is not restored, and imported rows would not qualify for automatic pruning.

## The field-file convention

A content field is never inlined into JSON — it is written as a **sibling file** holding the
**exact stored column value**, and the JSON links to it with a `*_file` key. The convention is
identical in every branch:

- `contents.md` — raw Markdown (scene prose, `contents` column, verbatim — **not** rendered).
- `description.html`, `notes.html` — the stored **sanitized HTML fragment** (no `<!doctype>`,
  no wrapper, not re-rendered).
- `dedication.md`, `acknowledgements.md`, `preface.md`, `postface.md` — a book's four
  front-/back-matter fields, raw Markdown like `contents.md` (never rich HTML, never
  re-rendered). Read on import through the same Markdown sanitizer gate as a scene's
  `contents.md`.
- `rights.txt` — a book's `rights`. A `text` column, but **plain**, not rich: written as
  `.txt` and never `.html`, so the importer's sanitizer does not treat it as a fragment.
- `cover/<name>` — a plain-path cover image (project, book or chapter), co-located with its
  owner exactly like a codex entry's `cover/…`.

> [!IMPORTANT]
> A **null or empty** content field omits **both** the file and its `*_file` key. This
> null-handling rule is identical for every entity and every branch — never write an empty
> field file or a dangling link.

A `cover_file` link is the one deliberate exception to "the file is always there": the link is
written whenever a cover is set, but the **bytes** ship only when the export includes media. A
metadata-only export therefore declares `cover_file` and ships nothing, and the importer
restores a **null** cover. Cover columns carry no declared mime, so the import security gate
content-sniffs them on **bytes alone** (`finfo` + `getimagesize` must agree on an allowed image
type) — a forged image rejects the archive.

## The Project branch

`data/project/` carries only the project's **own** columns. The publication metadata that used
to live here belongs to each book now, and is never written at this level.

```
data/project/
  project.json            { id, name, daily_word_goal, total_word_goal,
                            description_file?, cover_file? }
  description.html
  cover/<name>            the dashboard card image (bytes only when media is included)
```

## The Books branch

One directory per book, in `position` order, each holding the book's own descriptor, its field
files, its EPUB publication config, and its whole manuscript. **Nesting mirrors ownership** —
the same rule the act → chapter → scene tree inside it already follows.

```
data/books/<id>-slug/
  book.json               (see below)
  description.html
  rights.txt
  dedication.md  acknowledgements.md  preface.md  postface.md
  cover/<name>            the EPUB cover (bytes only when media is included)
  publication-setting.json  (only when the book has a saved row)
  acts/<id>-slug/
    act.json              { id, name, position, book_id, description_file? }
    description.html
    chapters/<id>-slug/
      chapter.json        { id, name, position, act_id, description_file?, cover_file? }
      description.html
      cover/<name>        the chapter cover image bytes
      scenes/<id>-slug/
        scene.json        (see below)
        contents.md
        description.html
        notes.html
```

`book.json`:

```json
{
  "id": 4,
  "name": "The Long Winter",
  "position": 1,
  "project_id": 42,
  "language": "en",
  "author": "A. Writer",
  "publisher": "Small Press",
  "isbn": "978-3-16-148410-0",
  "overview_render_mode": "chapter",
  "description_file": "description.html",
  "rights_file": "rights.txt",
  "dedication_file": "dedication.md",
  "acknowledgements_file": "acknowledgements.md",
  "preface_file": "preface.md",
  "postface_file": "postface.md",
  "cover_file": "cover/front.jpg"
}
```

| Key                     | Notes                                                                     |
|-------------------------|---------------------------------------------------------------------------|
| `name`                  | **Nullable, and written literally as `null`** — see the warning below.     |
| `language`              | The `BookLanguage` **enum value** (e.g. `"en"`).                           |
| `overview_render_mode`  | The `StoryOverviewMode` **enum value** (`"chapter"` or `"whole"`).         |
| `position`              | Order within the project; replayed verbatim on import.                     |
| `*_file`                | Present only when the field is non-empty (the null-handling rule).         |

> [!WARNING]
> **`name: null` must survive the round trip.** A null name means "this book has no name of
> its own" and tracks the project's through every rename (see
> [Architecture](../architecture/README.md)). Coercing it to a string on export or on
> import materializes the value and permanently breaks that tracking. It is the one key here
> that may legitimately be `null` rather than absent.

> [!WARNING]
> **The order books are imported in is load-bearing.** `Book::created` copies the project's
> name onto every unnamed sibling, so inserting a book rewrites a `null` name already restored
> beside it. `ProjectGraphImporter::importStory()` sorts the archive's books by
> `(position, id)` — `glob()` returns directory order, which is not position order —
> reconciles the **first** onto the project's auto-created book with `update()` (which fires no
> `created` event), then inserts the rest. Reverse it and a two-book import silently renames
> book one.

`scene.json`:

```json
{
  "id": 87,
  "name": "The Confrontation",
  "position": 2,
  "status": "to_edit",
  "chapter_id": 12,
  "event_id": 40,
  "mentioned_event_ids": [41, 55],
  "contents_file": "contents.md",
  "description_file": "description.html",
  "notes_file": "notes.html"
}
```

| Key                   | Notes                                                                        |
|-----------------------|------------------------------------------------------------------------------|
| `status`              | The `SceneStatus` **enum value** (machine form, e.g. `"to_edit"`), not the label. |
| `event_id`            | The "happens during" event (nullable), by stable id.                         |
| `mentioned_event_ids` | Ids from the `event_scene` pivot — events referenced but not the primary one. |
| `*_file`              | Present only when the field is non-empty (see the null-handling rule above).  |

> [!NOTE]
> `event_id` / `mentioned_event_ids` are recorded as **raw ids even though the Timeline
> branch is written separately**. Events stay project-scoped, so a scene in book 2 may point
> at the same event as one in book 1. Export just records the ids; an import resolves them
> after loading events.

The scene share-link columns (`share_token`, `share_expires_at`) are **deliberately excluded**
— they are per-deployment secrets, not manuscript content.

> [!NOTE]
> **Codex references are excluded too, for a different reason.** `scene_codex_entry` (which
> codex entries a scene's contents mention — see [`codex.md` → Scene
> references](../features/codex.md#scene-references)) is a derived cache, not source-of-truth content: it is fully recomputed from
> `contents` and the Codex branch's aliases/names, so the exporter never writes it, and an
> archive predating this feature imports and re-derives references identically to a newer one.
> Do not add `codex_entry_ids` to `scene.json` — see `ProjectImporter::run()`, which calls
> `SceneReferenceMatcher::syncProject()` once after the graph-import phases, for where the
> recomputation happens after import.

### `<book>/publication-setting.json`

The book's EPUB **publication settings** — the include-toggles, formatting choices, section
order and appendix options from the Export-ebook config page (the
`App\Models\PublicationSetting` row, one per **book**).

Written **only when the book has a saved `publication_settings` row**. A book that never
visited the config form omits the file entirely; its lossless meaning is "the lazy default"
(`Book::publicationSettingOrDefault()`).

```json
{
  "include_book_cover": true,
  "include_chapter_covers": false,
  "include_scene_titles": false,
  "include_act_descriptions": false,
  "include_chapter_descriptions": false,
  "include_scene_descriptions": false,
  "include_dedication": false,
  "include_acknowledgements": false,
  "include_preface": false,
  "include_postface": false,
  "include_author": true,
  "include_publisher": true,
  "include_rights": true,
  "include_isbn": true,
  "chapter_title_format": "chapter_number_title",
  "table_of_contents_depth": "chapters",
  "divider_type": "horizontal_rule",
  "section_order": ["title", "dedication", "acknowledgements", "preface", "toc", "body", "postface", "appendix"],
  "include_codex_appendix": false,
  "appendix_entry_types": [],
  "appendix_include_images": false
}
```

Every value is the **raw persisted column value** (booleans as booleans, the three
enum columns as their backing string, the two ordered lists as arrays) — never
rendered. `book_id`, `id`, and timestamps are deliberately **omitted**: the
import remaps the setting onto the freshly-created book.

> [!IMPORTANT]
> **This descriptor is validated as UNTRUSTED input on import, never trusted.**
> Unlike the content descriptors, a malformed publication setting must **never
> fail the whole import** — the config is a presentation preference. The importer
> validates it against the exact rules the config form uses
> (`UpdatePublicationSettingRequest::configRules()`); on **any** failure —
> unreadable/non-object JSON, an illegal enum, a broken `section_order` — it
> **logs, skips that book's config, and imports the content anyway**, leaving the
> book on the lazy default. Unknown `appendix_entry_types` are the one soft case:
> individual unknown codex types are **dropped**, and the rest of the config still
> applies. `ArchiveValidator` allow-lists the path but does **not** schema-check its
> content — that is entirely the importer's job.

> [!NOTE]
> **The codex appendix carries no archive artifact of its own.** The three appendix
> fields (`include_codex_appendix`, `appendix_entry_types`, `appendix_include_images`)
> are pure EPUB-render *preferences* — at export time the appendix pages are built from
> the entries and media already written under the **Codex branch** below, filtered to the
> ones this book's scenes reference. So the round-trip needs nothing beyond these three
> booleans/arrays: an imported book re-renders the appendix from the restored Codex and
> its own restored prose.

## `data/word-count-snapshots.json`

The project's writing history — one row per writer-day, cumulative total, **across every
book** (goals and history are project-level). A flat array like `data/tags.json`, ordered
oldest first:

```json
[
  { "recorded_on": "2026-08-01", "word_count": 1200 },
  { "recorded_on": "2026-08-02", "word_count": 1900 }
]
```

Always written, even as `[]` — "no history" is a real, representable state, not a lazy
default. Restored in bulk (`DB::table('word_count_snapshots')->insert(...)`, never through the
model) so no `WordCountSnapshotRecorder` event fires on top of the restored rows.

## The Timeline branch

The project's chronology — every **plotline** and **event**, under `data/timeline/`. Shared by
every book: there is one Start/End bookend pair per project, not per volume.

- **Not nested**, unlike the Books branch: an event belongs to many plotlines, not one, so
  both live in flat type folders.
- Each entity is a `<id>-slug` directory with a JSON descriptor plus its raw
  `description.html` fragment — same field-file and null-handling rules as everywhere else.

> [!IMPORTANT]
> The auto-created **anchors are exported like any other row**: the `is_main` **main
> plotline** and the two `is_fixed` **Start/End bookend events** every project is seeded
> with. They are part of the graph — a scene's `event_id` or a Codex attribute value's
> `start_event_id` (see the Codex branch) frequently points at the Start bookend, so their
> directories and ids must exist in `data/`.

### Layout & shapes

```
data/timeline/plotlines/<id>-slug/
  plotline.json           { id, name, color, is_main, project_id, description_file? }
  description.html
data/timeline/events/<id>-slug/
  event.json              (see below)
  description.html
```

`plotline.json`:

| Key                | Notes                                                                     |
|--------------------|---------------------------------------------------------------------------|
| `color`            | Hex string (e.g. `"#3b82f6"`), from `App\Support\PlotlineColors`.          |
| `is_main`          | Boolean. `true` for the single auto-created "Main plotline"; part of the lossless contract. |
| `description_file` | Present only when `description` is non-empty (the null-handling rule).     |

`event.json`:

```json
{
  "id": 40,
  "title": "The Great Battle",
  "event_datetime": "2026-05-01T09:30:00+00:00",
  "is_fixed": false,
  "project_id": 42,
  "plotline_ids": [7, 9],
  "description_file": "description.html"
}
```

| Key              | Notes                                                                        |
|------------------|------------------------------------------------------------------------------|
| `title`          | Events have no `name` column — the directory slug is built from `title`.      |
| `event_datetime` | A stable **ISO-8601** string (the `datetime` cast serialized).               |
| `is_fixed`       | Boolean. `true` for the Start/End bookends; part of the lossless contract.   |
| `plotline_ids`   | Ids from the `event_plotline` pivot (`Event::plotlines`), by stable id.       |

> [!NOTE]
> **Import-time dedup concern.** The app auto-creates the main plotline, the Start/End
> bookends and the first **book** whenever a project is created (`Project::booted()`).
> Import therefore **matches those seeded rows rather than duplicating them** — it reuses the
> existing `is_main` plotline, the earliest/latest `is_fixed` events and the auto-created
> book — and remaps the archive's ids onto them. The export just records them faithfully;
> reconciliation is the importer's job (`App\Services\Import\ProjectGraphImporter`).

## The Codex branch

The project's world bible: every **Codex entry** (characters, locations, organizations) plus
its **aliases**, **tags**, **attribute values over time** and **media**, alongside the
project's flat **attribute definitions** and **tag** lists. Shared across books, and the
richest branch — the one carrying the *attribute-over-time* relationship.

### Layout & shapes

```
data/codex/attributes.json   flat list of attribute DEFINITIONS (see below)
data/tags.json               flat list of { id, name } tags
data/codex/<type>/<id>-slug/
  entry.json                 (see below)
  description.html           raw stored HTML fragment (omitted when null)
  cover/<original-name>                       (media bytes, only when the toggle is on)
  reference-images/NN-<original-name>         (media bytes, only when the toggle is on)
  reference-files/NN-<original-name>          (media bytes, only when the toggle is on)
```

`<type>` is the `CodexEntryType` **enum value** (`character`, `location`, `organization`),
so entries are grouped by type. Same `<id>-slug`, field-file, and null-handling rules as the
other branches.

`data/codex/attributes.json` — the project's attribute **definitions** (not values), a flat
array ordered by `position`. These are the columns the "attribute values" below fill in:

```json
[
  { "id": 3, "name": "Age", "applies_to": ["character"], "position": 1 }
]
```

| Key          | Notes                                                                             |
|--------------|-----------------------------------------------------------------------------------|
| `applies_to` | List of `CodexEntryType` **enum values** the attribute appears on (e.g. `["character","location"]`). |
| `position`   | Display order on the sheet, scoped to the project (the app-wide ordering invariant). |

`data/tags.json` — the project's tags as a flat array; an entry's `tag_ids` reference these
by stable id:

```json
[ { "id": 8, "name": "protagonist" } ]
```

`entry.json`:

```json
{
  "id": 21,
  "name": "Alice Harker",
  "type": "character",
  "project_id": 42,
  "aliases": ["Ally", "The Wanderer"],
  "tag_ids": [8, 11],
  "attribute_values": [
    { "id": 5, "attribute_id": 3, "start_event_id": 40, "value": "29" }
  ],
  "media": [
    {
      "id": 71,
      "collection": "cover",
      "position": 1,
      "original_name": "portrait.jpg",
      "mime_type": "image/jpeg",
      "size": 84213,
      "file": "cover/portrait.jpg"
    }
  ],
  "description_file": "description.html"
}
```

| Key                | Notes                                                                          |
|--------------------|--------------------------------------------------------------------------------|
| `type`             | The `CodexEntryType` **enum value** (matches the `<type>` folder).             |
| `aliases`          | Plain array of alias strings (`CodexEntry::aliases`).                          |
| `tag_ids`          | Ids from the `codex_entry_tag` pivot, referencing `data/tags.json`.           |
| `attribute_values` | See the attribute-over-time note below.                                       |
| `media`            | The media manifest — see the media note below.                                |
| `description_file` | Present only when `description` is non-empty (the null-handling rule).         |

> [!IMPORTANT]
> **Attribute values are anchored to events.** Each `attribute_values[]` row is
> `{ id, attribute_id, start_event_id, value }`: the entry's value for `attribute_id`
> **takes effect from** the event `start_event_id` (frequently the Start bookend — see the
> Timeline branch) and holds until a later-anchored value supersedes it. This event anchoring
> is the heart of the lossless "attribute over time" model — the value is *not* a plain
> scalar on the entry, it is a timeline of `(attribute, start event) → value`. `attribute_id`
> references `data/codex/attributes.json`; `start_event_id` references the Timeline events
> branch. Export records the raw ids; an import resolves them after loading attributes/events.

### Media & the "Include images & files" toggle

Media live only on Codex entries (`codex_media`), in three collections: `cover` (single),
`reference_image`, `reference_file`. Project, book and chapter covers are plain path columns
instead — see the field-file convention above.

- Each row is described in the entry's `media[]` array — **`entry.json` IS the manifest**;
  there is deliberately no separate `images/manifest.json`.
- Each `file` is a path **relative to the entry directory**, grouped by collection.
- The single cover keeps its original name (`cover/portrait.jpg`). The multi-item reference
  collections prefix a zero-padded position so two files with the same original name never
  collide (`reference-images/01-sketch.png`, `reference-files/01-notes.pdf`).

> [!IMPORTANT]
> The **"Include images & files" toggle governs BYTES only**. The `media[]` **metadata is
> always written** — with the toggle **off**, `entry.json` still lists every media row
> (collection, original name, mime, size, `file`), but the byte files are **absent** from the
> archive. With the toggle **on**, every collection's bytes (including non-image
> `reference_file`s like PDFs) are copied verbatim to their `file` path — no thumbnailing,
> resizing, or transform. Bytes are read straight off the `public` disk, never the `/storage`
> URL, so the export needs no `php artisan storage:link` (invariant 5).

## The `books/` reading layer

`books/` is the **human reading version** of the manuscript: deliberately narrow — just the
prose, readable — and **not** a source of truth. Import ignores it entirely and reconstructs
the project from `data/`.

> [!IMPORTANT]
> **`books/` is the ONE place the export renders Markdown to HTML.** Each scene's `contents`
> column (raw CommonMark) is rendered with `Str::markdown()` — the same render path the app uses
> on the Story overview and the shared-scene page. `data/` never renders anything (invariant 3);
> `books/` renders only scene `contents`. It carries **no** descriptions, notes, images, statuses,
> events, or Codex/Timeline data — those live only, raw, in `data/`.

### Layout

```
books/index.html         lists every book, linking its own table of contents
books/NN/                one folder per book, named by the book's zero-padded position
books/NN/index.html      that book's table of contents (acts + chapter links)
books/NN/NN/             one folder per act, named by the act's zero-padded position
books/NN/NN/NN.html      one compiled page per chapter, named by the chapter's
                         zero-padded PER-ACT position
```

Every number comes from the app-wide `position` column, zero-padded to two digits:

- The book folder uses the **book** position (its order within the project).
- The act folder uses the **act** position (its order within that book).
- The chapter file uses the chapter's **per-act** position — positions restart at `01` inside
  each act, so act 2's first chapter is `02/01.html`, not a global `03`.
- Reordering positions in the app renumbers the files on the next export.

`StaticSiteExporter::chapterHref()` still builds `%02d/%02d.html`, unchanged: it is now
relative to the book's own folder, so the file-identity rule (raw `position`, never a derived
number) survived the book layer untouched — only the folder above it is new.

### `books/index.html` — the project index

Every book, in `position` order, linking `NN/index.html`. Titles come from
`Book::displayName()`, so an unnamed book gets the project's name rather than a blank link.
Every project holds at least one book, so this list is never empty.

### `books/NN/index.html` — one book's table of contents

- Lists every **act** of that book (title as a heading) with its **chapters** as links to the
  compiled pages, in `position` order.
- Links are relative to the book's folder (`01/01.html`, `02/01.html`).
- Act and chapter titles are **plain text and HTML-escaped** — the title columns are not rich
  fields. Chapter headings are formatted through the book's own
  `PublicationSetting::chapter_title_format`, the same setting the EPUB obeys, and carry the
  book-wide number from `StoryNumbering` as described in [Architecture](../architecture/README.md#manuscript-ordering-and-numbering).
- A book with no acts still emits a valid `index.html`, with no chapter links.

### `books/NN/NN/NN.html` — a compiled chapter page

Each chapter page contains:

- the **chapter title** (plain text, HTML-escaped) as an `<h1>`;
- each scene's `contents` **rendered Markdown → HTML**, in `position` order, **joined by `<hr>`**
  — with **no scene titles** (the reading layer is continuous prose, not a scene-by-scene index);
- **prev/next** reading links at **both the top and the bottom** of the page.

Prev/next follow the reading order across act boundaries, but **never leave the book** — a book
is a reading unit:

- The last chapter of act *n* links forward to the first chapter of act *n+1*, in the same book.
- Chapter pages sit one level below their book's TOC, so a sibling link is `../NN/NN.html`,
  crossing into another act's folder when needed (`../02/01.html`).
- At the ends, the first chapter's *prev* and the last chapter's *next* both point at
  `../index.html` — that book's own TOC, not the next book.

Pages are **self-contained**: one full HTML document each, **minimal inline CSS** (readable
serif body, constrained `max-width`), **no external assets** — so a page opens directly from
the unzipped archive. The HTML lives in Blade templates under `resources/views/exports/books/`
(`layout`, `books-index`, `index`, `chapter`), rendered to string by `StaticSiteExporter`. HTML
is never string-built in the service.
