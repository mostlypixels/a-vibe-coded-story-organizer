# Export format — the `data/` contract

This page is the specification for a project **export**, and therefore the contract
the **import** feature reads (built — see [`architecture.md` → Static site import](architecture.md#static-site-import)).
A `.zip` produced from **Admin → Export & import → Export** contains a top-level
`README.md` plus two folders:

- **`README.md`** — the archive's front door: the project name, the export date, the
  project description as plain text (the stored HTML stripped to prose), and a short note
  sending humans to `book/` and machines to `data/`. Courtesy only; never a source of truth.
- **`book/`** — a human reading version (TOC + compiled chapter pages). Presentation
  only; never the source of truth. Specified in [The `book/` reading layer](#the-book-reading-layer)
  below.
- **`data/`** — a **lossless**, machine-readable copy of the project, built to be
  reconstructed exactly by import. This document specifies `data/`.

> [!IMPORTANT]
> `data/` is **raw and lossless**. Every field file carries the **exact stored column
> value** — never re-rendered, re-sanitized, or reformatted. Only the `book/` layer
> renders Markdown to HTML. Do not blur the two.

## Stable identifiers

Every entity's identity is its **database primary key**, written into its JSON and
used for every cross-reference (`event_id`, `chapter_id`, `plotline_ids`, …). An
import remaps these ids to freshly-inserted rows. Directory-name slugs (`<id>-slug`)
are **cosmetic** — never rely on a slug or filename for identity.

## `data/manifest.json`

The archive's root descriptor, written once per export:

```json
{
  "version": 1,
  "project_id": 42,
  "exported_at": "2026-07-09T14:03:11+00:00",
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
an importer must ignore keys it does not recognize. The current version is **1**.

## The Story branch

The manuscript tree — the project plus its `act → chapter → scene` hierarchy. **Nesting
mirrors ownership**: a chapter directory lives inside its act, a scene inside its chapter.
Every entity is a `<id>-slug` directory containing one **JSON descriptor** (scalars, stable
ids, relationship id lists, and links to its field files) plus its **raw field files**.

### The field-file convention

A content field is never inlined into JSON — it is written as a **sibling file** holding the
**exact stored column value**, and the JSON links to it with a `*_file` key:

- `contents.md` — raw Markdown (scene prose, `contents` column, verbatim — **not** rendered).
- `description.html`, `notes.html` — the stored **sanitized HTML fragment** (no `<!doctype>`,
  no wrapper, not re-rendered).

> [!IMPORTANT]
> A **null or empty** content field omits **both** the file and its `*_file` key. This
> null-handling rule is identical for every entity and every branch — never write an empty
> field file or a dangling link.

### Layout & shapes

```
data/project/
  project.json            { id, name, description_file? }
  description.html
data/acts/<id>-slug/
  act.json                { id, name, position, project_id, description_file? }
  description.html
  chapters/<id>-slug/
    chapter.json          { id, name, position, act_id, description_file? }
    description.html
    scenes/<id>-slug/
      scene.json          (see below)
      contents.md
      description.html
      notes.html
```

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
> branch is written separately**. Export just records the ids; an import resolves them after
> loading events. The scene never needs the event directories to exist.

The scene share-link columns (`share_token`, `share_expires_at`) are **deliberately excluded**
— they are per-deployment secrets, not manuscript content.

> [!NOTE]
> **Codex references are excluded too, for a different reason.** `scene_codex_entry` (which
> codex entries a scene's contents mention — see `documentation/architecture.md` → *Scene
> references*) is a derived cache, not source-of-truth content: it is fully recomputed from
> `contents` and the Codex branch's aliases/names, so the exporter never writes it, and an
> archive predating this feature imports and re-derives references identically to a newer one.
> Do not add `codex_entry_ids` to `scene.json` — see `ProjectImporter::run()`, which calls
> `SceneReferenceMatcher::syncProject()` once after the graph-import phases, for where the
> recomputation happens after import.

## The Timeline branch

The project's chronology — every **plotline** and **event**, grouped by type under
`data/timeline/`. Unlike the Story branch these are **not nested** (an event belongs to
many plotlines, not one), so both live in flat type folders. Each entity is a `<id>-slug`
directory with a JSON descriptor plus its raw `description.html` fragment (same field-file
and null-handling rules as the Story branch above).

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
> **Import-time dedup concern.** The app auto-creates the main plotline and the Start/End
> bookends whenever a project is created (`Project::booted()`). Import therefore
> **matches those seeded rows rather than duplicating them** — it reuses the existing
> `is_main` plotline and the earliest/latest `is_fixed` events instead of inserting new
> ones — and remaps the archive's ids onto them. The export just records them faithfully;
> reconciliation is the importer's job (`App\Services\Import\ProjectGraphImporter::importTimeline`).

## The Codex branch

The project's world bible — every **Codex entry** (characters, locations, organizations)
plus its **aliases**, **tags**, **attribute values over time**, and **media**, together
with the project's flat **attribute definitions** and **tag** lists. This is the richest
branch, and the one that carries the feature's crucial *attribute-over-time* relationship.

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

Media live only on Codex entries (the `codex_media` table) in three collections: `cover`
(single), `reference_image`, and `reference_file`. Each row is described in the entry's
`media[]` array — **the entry.json IS the manifest; there is deliberately no separate
`images/manifest.json`.**

Each media entry's `file` is a path **relative to the entry directory**, grouped by
collection. The single cover keeps its original name (`cover/portrait.jpg`); the multi-item
reference collections prefix a zero-padded position so two files with the same original name
never collide (`reference-images/01-sketch.png`, `reference-files/01-notes.pdf`).

> [!IMPORTANT]
> The **"Include images & files" toggle governs BYTES only**. The `media[]` **metadata is
> always written** — with the toggle **off**, `entry.json` still lists every media row
> (collection, original name, mime, size, `file`), but the byte files are **absent** from the
> archive. With the toggle **on**, every collection's bytes (including non-image
> `reference_file`s like PDFs) are copied verbatim to their `file` path — no thumbnailing,
> resizing, or transform. Bytes are read straight off the `public` disk, never the `/storage`
> URL, so the export needs no `php artisan storage:link` (invariant 5).

## The `book/` reading layer

Everything above specifies `data/` — the raw, lossless machine layer. `book/` is the other
top-level folder: the **human reading version** of the manuscript. It is deliberately narrow —
just the prose, readable — and is **not** a source of truth. Import ignores it entirely
and reconstructs the project from `data/`.

> [!IMPORTANT]
> **`book/` is the ONE place the export renders Markdown to HTML.** Each scene's `contents`
> column (raw CommonMark) is rendered with `Str::markdown()` — the same render path the app uses
> on the Story overview and the shared-scene page. `data/` never renders anything (invariant 3);
> `book/` renders only scene `contents`. It carries **no** descriptions, notes, images, statuses,
> events, or Codex/Timeline data — those live only, raw, in `data/`.

### Layout

```
book/index.html          the table of contents (acts + chapter links)
book/NN/                 one folder per act, named by the act's zero-padded position
book/NN/NN.html          one compiled page per chapter, named by the chapter's
                         zero-padded PER-ACT position
```

Both numbers come straight from the app-wide `position` column, zero-padded to two digits. The
act folder uses the **act** position; the chapter file uses the chapter's **per-act** position
(chapter positions restart at `01` inside each act — act 2's first chapter is `02/01.html`, not a
global `03`). Reordering positions in the app renumbers the files on the next export.

### `book/index.html` — the table of contents

Lists every **act** (its title as a heading) with its **chapters** (titles) as links to the
compiled chapter pages, in `position` order. Links are relative to `book/` (e.g.
`01/01.html`, `02/01.html`). Act and chapter titles are **plain text and HTML-escaped** — the
title columns are not rich fields. An empty project still emits a valid `index.html` with no
chapter links.

### `book/NN/NN.html` — a compiled chapter page

Each chapter page contains:

- the **chapter title** (plain text, HTML-escaped) as an `<h1>`;
- each scene's `contents` **rendered Markdown → HTML**, in `position` order, **joined by `<hr>`**
  — with **no scene titles** (the reading layer is continuous prose, not a scene-by-scene index);
- **prev/next** reading links at **both the top and the bottom** of the page.

Prev/next follow the **global reading order across act boundaries**: the last chapter of act *n*
links forward to the first chapter of act *n+1*. Because chapter pages sit one level below
`index.html`, a sibling chapter link is `../NN/NN.html` (crossing into another act's folder when
needed, e.g. `../02/01.html`), and at the ends the first chapter's *prev* and the last chapter's
*next* point back to the TOC at `../index.html`.

Pages are **self-contained**: a single full HTML document each, with **minimal inline CSS** (a
readable serif body, a constrained `max-width`) and **no external assets**, so a page opens
directly from the unzipped archive. The HTML lives in Blade templates under
`resources/views/exports/book/` (`layout`, `index`, `chapter`) rendered to string by
`StaticSiteExporter` — HTML is never string-built in the service.
