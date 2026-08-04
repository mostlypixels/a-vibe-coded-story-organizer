# EPUB export — deep dive

The short version lives in [`architecture.md` → EPUB export](architecture.md#epub-export-publication-settings).
This page is the reference: the isolation rules, the library constraint that shapes `Acts`
depth, and the regression guard every change must keep green.

## What owns what

**Admin → Export & import → Export → Ebook** downloads a project as a standard `.epub`, built
by `App\Services\EpubExporter` on `rampmaster/phpepub`. Like `StaticSiteExporter` it is
**HTTP-agnostic** — takes a `Project`, returns a finished temp-file path, cleans up on
exception — so a queued job could reuse it.

| Owner | Responsibility |
|---|---|
| The library | The mechanical package: mimetype, `container.xml`, OPF metadata/manifest/spine, EPUB 3 nav, NCX, the zip |
| `EpubExporter` | The **content**: every XHTML document (Blade under `resources/views/exports/epub/`, never string-built in PHP), the CSS, the metadata *values*, the navigation shape |

## Two isolation rules — do not "simplify" these away

- **Scene bodies and the four front-/back-matter Markdown pages render through the service's
  own private SmartPunct `CommonMarkConverter`** (smart dashes/ellipses/quotes), *never*
  `Scene::renderedContents`. That accessor is the shared render path for the Story overview,
  the share page and the `book/` export, and must stay byte-for-byte identical.
- **Rich-HTML fields** (act/chapter/scene `description`, codex entry `description`) go through
  one shared `App\Support\RichText::toXhtmlFragment()` (`DOMDocument` load-HTML → save the
  body fragment as XML), so a sanitized-but-not-XHTML fragment — an unclosed `<p>`, a bare
  `<br>` — becomes well-formed. Embedding one raw would fail the package's XML gate.

Markdown fields never touch the XHTML helper; rich-HTML fields never touch the Markdown
converter.

> [!IMPORTANT]
> **`validatePackage()` is a hard gate, run inside every `export()`.** Every shipped `.xhtml`
> (this service's pages *and* the library's nav/cover pages) must parse with
> `DOMDocument::loadXML()`, and the OPF must validate against the vendored EPUB 3 RelaxNG
> schema (`resources/epub-schemas/` — no JVM/epubcheck at runtime). A failure is a
> **generator bug**: it throws a plain `RuntimeException` (let it 500 and be logged), *not*
> `EpubExportException`, which is reserved for the one user-facing case — a project whose
> skip-empty `filteredTree()` came back empty.

> [!WARNING]
> **What the gate does *not* check: whether the book reads sensibly.** It is XML
> well-formedness plus an OPF schema — not the nav's contents, not the TOC's, not whether a
> label says anything. A chapter with a blank name under `ChapterTitleFormat::Title` used to
> produce an *empty* nav label, and the package validated clean; the blank row only appeared
> in the reader (fixed in #56 by `chapterNavTitle()`'s fallback). Assume the same blind spot
> for any other "valid but meaningless" output, and cover those with export tests that open
> the produced `.epub` and assert on its contents — `EpubExportTest` has the `ZipArchive`
> helper for it.

## Continuous numbers and the full outline

`export()` builds `$tree` from `bookTree()` and derives `$numbering =
StoryNumbering::fromActs($tree)` once, threading it through every render/nav-label call —
so every act and chapter number in the package comes from that one tree. See
[`architecture.md` → Continuous numbering](architecture.md#continuous-numbering) for what
`StoryNumbering` is; this section covers what changed in the exporter to make its numbers
match the app's.

- **`bookTree()` (formerly `filteredTree()`) drops nothing.** A chapter with no scenes exports
  as a heading-only page (its number and title, no body); an act with no chapters keeps its
  divider page. Before this, the skip-empty filter meant a chapter's exported number shifted
  the moment an author filled in a placeholder that used to be skipped — export numbers now
  always equal app numbers.
- **The refusal guard moved.** `export()` no longer refuses because the tree came back empty
  after filtering — it refuses only when `hasAnyScene($tree)` is false, i.e. the project has
  no scene anywhere. A `EpubExportException` either way; the message is unchanged. Without
  this, a brand-new outline (acts and chapters, no prose yet) would export as a book of blank
  pages.
- **The `position` view key is `number` throughout the export layer** — `actViewData()`,
  `chapterViewData()`, `actNavTitle()`, `chapterNavTitle()`, and every partial that renders a
  heading. `ChapterTitleFormat::format(int $number, ?string $name)` takes the same derived
  number the website book layer uses (below), so a format choice reads identically in both
  exports.
- **`sceneNavTitle()` is the one nav label left alone** — still `"Scene {$scene->position}"`,
  per-chapter. It is the only place a scene number reaches a reader, and a project-wide count
  has no meaning under a chapter heading.

## `PublicationSetting` drives everything

The whole export is parameterised by one lazily-resolved `PublicationSetting`
(`Project::publicationSettingOrDefault()`, an **unsaved default** when the project has no
row). `export()` resolves it once and threads it through every private method.

All formatting/ordering choices live on **enums** — `ChapterTitleFormat::format()`,
`DividerType::dividerHtml()`, `TableOfContentsDepth::includesChapters()/includesScenes()` —
never a `match` on a raw string in the service or a view. `ChapterTitleFormat::format()` is
the single source shared by the chapter page heading *and* its nav/TOC label, so the two can't
drift.

> [!IMPORTANT]
> **Defaults reproduce the pre-feature output byte-for-byte.** Every metadata/cover toggle
> defaults `true`; every *new* rendering (scene titles, all descriptions, front/back matter,
> chapter covers, the codex appendix) defaults **off**. `EpubExporterTest`'s
> `defaults_v1_regression` exports the same project twice — once on the lazy default, once on
> an explicit `PublicationSetting::factory()` row — and asserts the two `.epub`s are
> content-identical. Keep it green: a new gated feature that is off-by-default cannot alter a
> default project's output.

> [!WARNING]
> **That guard normalises the OPF timestamps; it does not compare raw bytes.** The library
> stamps `dc:date` / `dcterms:modified` from `time()` at finalize, so two back-to-back exports
> straddling a wall-clock second differ by those two lines even when every content document is
> identical — under the parallel runner that raced into an intermittent failure. The guard
> unzips both packages and compares **entry-by-entry**, normalising only those two lines
> (`stripOpfTimestamps()`). Do not "restore" it to a single raw-byte `assertSame` — that is
> the latent flake, not a stricter check.

## The `section_order` walk

`addSections()` replaces what was once a hard-coded `title → toc → body` sequence. It walks
`PublicationSetting::section_order` (an ordered JSON array of component keys) and dispatches
each through a `match`:

| Key | Method | Notes |
| --- | --- | --- |
| `title` | `addTitleSection()` | Story title page. Always first (the model pins it). |
| `dedication`, `acknowledgements`, `preface`, `postface` | `addMatterSection()` | Markdown pages; one shared `matter.blade.php` driven by `MATTER_SECTIONS`. |
| `toc` | `addTocSection()` | The in-book TOC page — a real spine page, distinct from the reader-chrome nav. |
| `body` | `addBody()` | The act/chapter/scene tree: the manuscript. |
| `appendix` | `addAppendixSection()` | The optional codex appendix. |

Each front-/back-matter section renders **only when its `include_*` toggle is on AND the
Project's Markdown column is non-empty**. A disabled toggle *or* an empty field renders
nothing, and this "enabled AND has content" rule extends to the appendix. A toggle gates a
section **independently** of its position in the order.

## TOC/nav depth (`table_of_contents_depth`)

Changes the in-book TOC page (`renderToc()`) and the library nav that `addBody()` builds, in
lockstep:

- **`Chapters` (default)** — two-level Act → Chapter tree. Each act is its own divider page (a
  root nav entry); each chapter a nested page beneath it.
- **`Scenes`** — adds a third level. Chapter pages emit `id="scene-{id}"` anchors
  (`chapter-body.blade.php`, gated on this depth **only**, so the default stays byte-for-byte
  as before), and the nav hangs a per-scene entry under the chapter with href
  `chapter-{id}.xhtml#scene-{id}` — added with **null content**, which the library registers as
  an in-page anchor *without* a new spine page.
- **`Acts`** — the nav lists only acts. Not a simple "skip the chapter nav points" branch; see
  below.

> [!WARNING]
> **`rampmaster/phpepub` couples spine placement and nav entries — there is no
> spine-without-nav API.** Every content-bearing `addChapter()` adds a manifest item *and* a
> spine `itemref` *and* a nav point together; `NavPoint::setNavHidden(true)` is honoured by the
> NCX but **not** by the EPUB 3 nav document (confirmed by a standalone spike). You cannot put
> a chapter page in the reading order at `Acts` depth while keeping it out of the nav.
>
> So `Acts` depth renders **one combined spine page per act** (`renderActWithChapters()` →
> `act-combined.blade.php`): the act divider plus all its chapters in a single `act-{id}.xhtml`
> (each chapter still its own `<section>` for the page-break CSS). One `addChapter()` per act ⇒
> exactly one nav entry per act, and **no standalone `chapter-{id}.xhtml` files are packaged**.
> The standalone shape is impossible here without shipping an unreadable book (chapters in
> neither spine nor nav) or fragile regex-surgery on the finalized nav.

`act-combined.blade.php` and the standalone `act`/`chapter` pages share their inner bodies via
`partials/act-body.blade.php` / `partials/chapter-body.blade.php` (plus the exporter's
`actViewData()` / `chapterViewData()` helpers), so the two paths can't drift.

## Chapter cover pages

`addChapterCoverPage()` inserts a full-page cover image before a chapter. Gated by three
conditions together: `include_chapter_covers`, a `cover_image` set on the chapter, and the file
still existing on the `public` disk — a missing file is skipped silently, so the export never
fails (mirroring the project cover).

- It is a **nav sibling** of the chapter (same level under the act, immediately before the
  chapter's own entry), *not* a child, so it never disturbs the `Scenes` depth's nested scene
  anchors.
- At `Acts` depth — no standalone chapter page — each cover is a root-level nav entry
  immediately before the combined act page holding that chapter.
- Image bytes go through the library's generic `addFile()` (manifest-only), namespaced
  `images/chapter-cover-{id}-{basename}`. **Never `setCoverImage()`** — that one-shot API is
  reserved for the single package-level project cover in `applyCover()`.

## The codex appendix

`addAppendixSection()` fills the reserved `appendix` slot: an optional back-matter appendix of
codex entries — a heading page (root nav entry) plus one page per entry nested beneath it
(entry name + `description` through `RichText::toXhtmlFragment()`).

A **true no-op** unless all three hold:

1. `include_codex_appendix` is on,
2. at least one `appendix_entry_types` is selected, and
3. the project actually has entries of those types (a lone heading with no entries is
   pointless).

Entries load filtered to the selected types, ordered `(type, name)`.

When `appendix_include_images` is on, each entry's `media` is eager-loaded (ordered
`(collection, position)`, matching the archive) and `addAppendixEntryImage()` embeds the
entry's **first image only** — deliberately the first media row that is *both* an `image/*`
MIME type *and* has bytes on disk, so a metadata-only imported row (null path) or a non-image
reference file (a PDF) is skipped rather than embedded as a broken `<img>`. A missing file
returns null and the page renders text-only. When the toggle is off, `media` is never loaded.

Embedding all images, and a `Review` entity, are explicit V2 non-goals.

## Publication settings (the model)

`App\Models\PublicationSetting` is **one lazy row per project**: never auto-created in
`booted()`, no backfill migration.

- `Project::publicationSettingOrDefault()` returns an unsaved instance whose field defaults
  match `PublicationSettingFactory::definition()` field-for-field. The two are what the
  defaults===v1 guard leans on — keep them in sync.
- The config form is `PublicationSettingController` + `UpdatePublicationSettingRequest`, whose
  shared `configRules()` static is the single rule set used by **both** the form's `rules()`
  and the archive importer's untrusted-config validation (see *Static site import* and
  [`export-format.md`](export-format.md)), so form and import can't validate differently.
- Authorization is the ordinary `ProjectPolicy` walk (`update` to write, `view` to
  read/export) — no new policy.
- `SECTION_KEYS` / `PINNED_FIRST_SECTION` and the `moveSectionUp/Down` helpers keep the
  sortable order's membership and pinning rules in one place.

## Review coverage — what has *not* been read closely

`EpubExporter` (1252 lines) is the largest single-responsibility stretch in the codebase: it
filters the tree, renders pages, packages, writes metadata, normalises the OPF, validates
against the RNG schema and generates filenames. The 2026-07 review of the revisions work read
it at method-signature level only, and `StaticSiteExporter` (856), `Import/ProjectGraphImporter`
(949) and `ArchiveValidator` (473) not at all.

Two consequences worth carrying:

- **Splitting packaging and validation out is a plausible next refactor**, but nobody has read
  enough of it to plan one. Treat the size as unexamined, not as endorsed.
- **The importer and the archive validator write entity rows directly**, bypassing the Form
  Requests. That is the door a blank `chapters.name` comes through even though `NOT NULL` plus
  `required` closes the form path — so export-side defences against odd data are not
  belt-and-braces (see the `> [!WARNING]` on `validatePackage()` above).
